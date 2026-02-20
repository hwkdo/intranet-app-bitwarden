<?php

use App\Models\Gvp;
use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;

use function Livewire\Volt\{state, title, computed, mount};

title('Bitwarden - GVP verwalten');

state([
    'loading' => false,
    'creatingGroupFor' => null,
    'creatingCollectionFor' => null,
    'search' => '',
]);

$apiService = computed(fn() => app(BitwardenPublicApiService::class));
$vaultApiService = computed(fn() => app(BitwardenVaultApiService::class));

$gvps = computed(fn() => Gvp::all());

$filteredGvps = computed(function () {
    $gvps = $this->gvps;

    if (empty($gvps)) {
        return [];
    }

    if (empty($this->search)) {
        return $gvps;
    }

    $search = strtolower($this->search);

    return $gvps->filter(function ($gvp) use ($search) {
        $bezeichnung = strtolower($gvp->bezeichnung);
        $name = strtolower($gvp->name ?? '');
        $kuerzel = strtolower($gvp->kuerzel ?? '');
        $nummer = strtolower($gvp->nummer ?? '');

        return str_contains($bezeichnung, $search)
            || str_contains($name, $search)
            || str_contains($kuerzel, $search)
            || str_contains($nummer, $search);
    })->values();
});

$createGroup = function (int $gvpId) {
    $this->creatingGroupFor = $gvpId;
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);

        if ($gvp->hasBitwardenGroup()) {
            Flux::toast('Diese GVP hat bereits eine Bitwarden-Gruppe', variant: 'warning');
            $this->loading = false;
            $this->creatingGroupFor = null;

            return;
        }

        // Lade alle Mitglieder für diese GVP
        $members = $gvp->getAllMembersForBitwarden();

        if (empty($members)) {
            Flux::toast('Keine Mitglieder für diese GVP gefunden', variant: 'warning');
            $this->loading = false;
            $this->creatingGroupFor = null;

            return;
        }

        // Lade alle existierenden Bitwarden-Mitglieder
        $bitwardenMembers = $this->apiService()->getMembers();
        $bitwardenMemberEmails = [];
        $bitwardenMemberIds = [];

        if (is_array($bitwardenMembers)) {
            foreach ($bitwardenMembers as $member) {
                if (isset($member['email'])) {
                    $bitwardenMemberEmails[strtolower($member['email'])] = $member['id'];
                    $bitwardenMemberIds[] = $member['id'];
                }
            }
        }

        // Sammle E-Mails der Mitglieder, die noch nicht in Bitwarden sind
        $emailsToInvite = [];

        foreach ($members as $member) {
            if (empty($member->email)) {
                continue;
            }

            $emailLower = strtolower($member->email);
            if (! isset($bitwardenMemberEmails[$emailLower])) {
                $emailsToInvite[] = $member->email;
            }
        }

        // Lade fehlende User ein, falls vorhanden
        if (! empty($emailsToInvite)) {
            try {
                $this->apiService()->inviteMembers([
                    'emails' => array_values($emailsToInvite),
                    'type' => '2', // User
                    'accessAll' => false,
                    'collections' => [],
                    'groups' => [],
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Fehler beim Einladen von Mitgliedern', [
                    'gvp_id' => $gvpId,
                    'emails' => $emailsToInvite,
                    'error' => $e->getMessage(),
                ]);
                // Weiter mit der Gruppenerstellung, auch wenn Einladungen fehlschlagen
            }
        }

        // Erstelle die Gruppe
        $groupName = $gvp->bezeichnung;
        $groupResponse = $this->apiService()->createGroup([
            'name' => $groupName,
            'accessAll' => false,
            'collections' => [],
            'users' => [],
        ]);

        $groupId = $groupResponse['id'] ?? null;

        if (! $groupId) {
            throw new \RuntimeException('Gruppe wurde erstellt, aber keine ID erhalten');
        }

        // Lade Bitwarden-Mitglieder erneut, um die neu eingeladenen zu finden
        sleep(1); // Kurze Pause, damit die API aktualisiert wird
        $updatedBitwardenMembers = $this->apiService()->getMembers();
        $allMemberIds = [];

        if (is_array($updatedBitwardenMembers)) {
            foreach ($updatedBitwardenMembers as $member) {
                if (isset($member['email']) && isset($member['id'])) {
                    $allMemberIds[strtolower($member['email'])] = $member['id'];
                }
            }
        }

        // Sammle alle Member-IDs, die zur Gruppe hinzugefügt werden sollen
        $userIdsToAdd = [];

        foreach ($members as $member) {
            if (empty($member->email)) {
                continue;
            }

            $emailLower = strtolower($member->email);
            if (isset($allMemberIds[$emailLower])) {
                $userIdsToAdd[] = $allMemberIds[$emailLower];
            }
        }

        // Füge alle Mitglieder zur Gruppe hinzu
        if (! empty($userIdsToAdd)) {
            $this->apiService()->updateGroupUsers($groupId, $userIdsToAdd);
        }

        // Speichere die Group-ID in der GVP
        $gvp->update(['bitwarden_group_id' => $groupId]);

        Flux::toast('Gruppe erfolgreich erstellt und Mitglieder hinzugefügt', variant: 'success');
        unset($this->gvps);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Erstellen der Bitwarden-Gruppe', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Erstellen der Gruppe: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
        $this->creatingGroupFor = null;
    }
};

$createCollection = function (int $gvpId) {
    $this->creatingCollectionFor = $gvpId;
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);

        if ($gvp->hasBitwardenCollection()) {
            Flux::toast('Diese GVP hat bereits eine Bitwarden-Collection', variant: 'warning');
            $this->loading = false;
            $this->creatingCollectionFor = null;

            return;
        }

        if (! $gvp->hasBitwardenGroup()) {
            Flux::toast('Für diese GVP muss zuerst eine Gruppe erstellt werden', variant: 'warning');
            $this->loading = false;
            $this->creatingCollectionFor = null;

            return;
        }

        // Erstelle die Collection mit der Gruppe verknüpft
        $collectionName = $gvp->bezeichnung;
        $groupId = $gvp->bitwarden_group_id;

        $collectionData = [
            'name' => $collectionName,
            'externalId' => 'gvp-'.$gvp->id,
            'groups' => [
                [
                    'id' => $groupId,
                    'readOnly' => false,
                    'hidePasswords' => false,
                    'manage' => false,
                ],
            ],
            'users' => [],
        ];

        $collectionResponse = $this->vaultApiService()->createCollection($collectionData);

        $collectionId = $collectionResponse['id'] ?? null;

        if (! $collectionId) {
            // Versuche alternative Strukturen zu parsen
            if (isset($collectionResponse['data']['id'])) {
                $collectionId = $collectionResponse['data']['id'];
            } else {
                throw new \RuntimeException('Collection wurde erstellt, aber keine ID erhalten');
            }
        }

        // Speichere die Collection-ID in der GVP
        $gvp->update(['bitwarden_collection_id' => $collectionId]);

        Flux::toast('Collection erfolgreich erstellt', variant: 'success');
        unset($this->gvps);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Erstellen der Bitwarden-Collection', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Erstellen der Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
        $this->creatingCollectionFor = null;
    }
};

$deleteCollection = function (int $gvpId) {
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);

        if (! $gvp->hasBitwardenCollection()) {
            Flux::toast('Diese GVP hat keine Bitwarden-Collection', variant: 'warning');

            return;
        }

        $collectionId = $gvp->bitwarden_collection_id;

        // Collection in Bitwarden löschen
        $this->vaultApiService()->deleteCollection($collectionId);

        // Collection-ID in der GVP zurücksetzen
        $gvp->update(['bitwarden_collection_id' => null]);

        Flux::toast('Collection erfolgreich gelöscht', variant: 'success');
        unset($this->gvps);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Löschen der Bitwarden-Collection', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Löschen der Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$deleteGroup = function (int $gvpId) {
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);

        if (! $gvp->hasBitwardenGroup()) {
            Flux::toast('Diese GVP hat keine Bitwarden-Gruppe', variant: 'warning');

            return;
        }

        if ($gvp->hasBitwardenCollection()) {
            Flux::toast('Bitte zuerst die Collection löschen, bevor die Gruppe gelöscht werden kann', variant: 'warning');

            return;
        }

        $groupId = $gvp->bitwarden_group_id;

        // Gruppe in Bitwarden löschen
        $this->apiService()->deleteGroup($groupId);

        // Group-ID in der GVP zurücksetzen
        $gvp->update(['bitwarden_group_id' => null]);

        Flux::toast('Gruppe erfolgreich gelöscht', variant: 'success');
        unset($this->gvps);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Löschen der Bitwarden-Gruppe', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Löschen der Gruppe: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="GVP verwalten" subheading="Bitwarden GVP">
    <flux:card class="glass-card">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">GVPs</flux:heading>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="GVPs durchsuchen..."
            icon="magnifying-glass"
            class="mb-6"
        />

        @if(empty($this->filteredGvps))
            <flux:callout variant="info" icon="information-circle">
                @if(empty($this->search))
                    Keine GVPs gefunden.
                @else
                    Keine GVPs gefunden, die "{{ $this->search }}" enthalten.
                @endif
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Bezeichnung</flux:table.column>
                    <flux:table.column>Mitglieder</flux:table.column>
                    <flux:table.column>Gruppe</flux:table.column>
                    <flux:table.column>Collection</flux:table.column>
                    <flux:table.column align="end">Aktionen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->filteredGvps as $gvp)
                        @php
                            $memberCount = $gvp->memberCount();
                            $hasGroup = $gvp->hasBitwardenGroup();
                            $hasCollection = $gvp->hasBitwardenCollection();
                            $isCreatingGroup = $this->creatingGroupFor === $gvp->id;
                            $isCreatingCollection = $this->creatingCollectionFor === $gvp->id;
                        @endphp
                        <flux:table.row wire:key="gvp-{{ $gvp->id }}">
                            <flux:table.cell>
                                <flux:heading size="sm">{{ $gvp->bezeichnung }}</flux:heading>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $memberCount }} {{ $memberCount === 1 ? 'Mitglied' : 'Mitglieder' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($hasGroup)
                                    <flux:badge variant="success">Ja</flux:badge>
                                @else
                                    <flux:badge variant="danger">Nein</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($hasCollection)
                                    <flux:badge variant="success">Ja</flux:badge>
                                @else
                                    <flux:badge variant="danger">Nein</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-2">
                                    @if(!$hasGroup)
                                        <flux:button
                                            wire:click="createGroup({{ $gvp->id }})"
                                            variant="primary"
                                            size="sm"
                                            wire:loading.attr="disabled"
                                            wire:target="createGroup({{ $gvp->id }})"
                                        >
                                            @if($isCreatingGroup)
                                                <span>Wird erstellt...</span>
                                            @else
                                                Gruppe erstellen
                                            @endif
                                        </flux:button>
                                    @endif

                                    @if($hasGroup && !$hasCollection)
                                        <flux:button
                                            wire:click="createCollection({{ $gvp->id }})"
                                            variant="primary"
                                            size="sm"
                                            wire:loading.attr="disabled"
                                            wire:target="createCollection({{ $gvp->id }})"
                                        >
                                            @if($isCreatingCollection)
                                                <span>Wird erstellt...</span>
                                            @else
                                                Collection erstellen
                                            @endif
                                        </flux:button>
                                    @endif

                                    @if($hasCollection)
                                        <flux:button
                                            wire:click="deleteCollection({{ $gvp->id }})"
                                            wire:confirm="Möchten Sie die Collection für diese GVP wirklich löschen?"
                                            variant="ghost"
                                            icon="trash"
                                            size="sm"
                                            class="text-red-600 hover:text-red-700"
                                        >
                                            Collection löschen
                                        </flux:button>
                                    @endif

                                    @if($hasGroup)
                                        <flux:button
                                            wire:click="deleteGroup({{ $gvp->id }})"
                                            wire:confirm="Möchten Sie die Gruppe für diese GVP wirklich löschen?"
                                            variant="ghost"
                                            icon="trash"
                                            size="sm"
                                            class="text-red-600 hover:text-red-700"
                                        >
                                            Gruppe löschen
                                        </flux:button>
                                    @endif
                                </div>

                                @if($hasGroup && $hasCollection)
                                    <flux:text variant="muted" size="sm">Vollständig eingerichtet</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
