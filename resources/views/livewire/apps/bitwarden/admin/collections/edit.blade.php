<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;

use function Livewire\Volt\{state, title, computed, mount};

title('Bitwarden - Collection bearbeiten');

state([
    'collectionId' => null,
    'name' => '',
    'originalName' => '',
    'externalId' => '',
    'selectedGroupIds' => [],
    'selectedMemberIds' => [],
    'loading' => false,
    'allGroups' => [],
    'allMembers' => [],
]);

$apiService = computed(fn() => app(BitwardenManagementApiInterface::class));
$vaultApiService = computed(fn() => app(BitwardenVaultApiService::class));

// Wenn der Name geändert wird, setze externalId automatisch auf den neuen Namen
$updatedName = function ($value) {
    // Nur aktualisieren, wenn sich der Name geändert hat
    if ($value !== $this->originalName) {
        $this->externalId = $value;
    }
};

mount(function (string $collectionId) {
    $this->collectionId = $collectionId;
    $this->loading = true;
    try {
        $response = $this->vaultApiService()->getCollection($collectionId);

        // Extrahiere die Collection aus der verschachtelten Struktur
        // Bitwarden API Format: ['success' => true, 'data' => {...}] oder direkt {...}
        $collection = $response;
        if (isset($response['data']) && is_array($response['data'])) {
            // Wenn data ein Objekt ist (nicht ein Array von Collections)
            if (isset($response['data']['object']) && $response['data']['object'] === 'collection') {
                $collection = $response['data'];
            } elseif (isset($response['data']['data']) && is_array($response['data']['data'])) {
                // Falls es doch ein Array ist, nimm das erste Element
                $collection = $response['data']['data'][0] ?? $response['data'];
            } else {
                $collection = $response['data'];
            }
        }

        if (empty($collection) || ! is_array($collection)) {
            Flux::toast('Collection nicht gefunden', variant: 'danger');
            return redirect()->route('apps.bitwarden.admin.collections.index');
        }

        $this->name = $collection['name'] ?? '';
        $this->originalName = $collection['name'] ?? '';
        $this->externalId = $collection['externalId'] ?? '';

        // Debug-Logging
        \Illuminate\Support\Facades\Log::debug('Bitwarden Collection loaded for edit', [
            'collectionId' => $collectionId,
            'raw_response' => $response,
            'extracted_collection' => $collection,
            'name' => $this->name,
            'externalId' => $this->externalId,
        ]);

        // Extrahiere Gruppen-IDs
        $groups = $collection['groups'] ?? [];
        if (is_array($groups)) {
            $this->selectedGroupIds = collect($groups)->map(function ($group) {
                return is_array($group) ? ($group['id'] ?? null) : $group;
            })->filter()->values()->toArray();
        } else {
            $this->selectedGroupIds = [];
        }

        // Extrahiere Benutzer-IDs
        $users = $collection['users'] ?? [];
        if (is_array($users)) {
            $this->selectedMemberIds = collect($users)->map(function ($user) {
                return is_array($user) ? ($user['id'] ?? null) : $user;
            })->filter()->values()->toArray();
        } else {
            $this->selectedMemberIds = [];
        }

        // Lade alle verfügbaren Gruppen
        try {
            $this->allGroups = $this->apiService()->getGroups();
            if (! is_array($this->allGroups)) {
                $this->allGroups = [];
            }
        } catch (\Exception $e) {
            $this->allGroups = [];
        }

        // Lade alle verfügbaren Mitglieder
        try {
            $membersResponse = $this->apiService()->getMembers();
            if (is_array($membersResponse)) {
                if (isset($membersResponse['data']) && is_array($membersResponse['data'])) {
                    $this->allMembers = $membersResponse['data'];
                } else {
                    $this->allMembers = $membersResponse;
                }
            } else {
                $this->allMembers = [];
            }
            if (! empty($this->allMembers) && is_array($this->allMembers)) {
                $this->allMembers = array_values($this->allMembers);
            }
        } catch (\Exception $e) {
            $this->allMembers = [];
        }
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Laden der Collection: '.$e->getMessage(), variant: 'danger');
        return redirect()->route('apps.bitwarden.admin.collections.index');
    } finally {
        $this->loading = false;
    }
});

$save = function () {
    $this->validate([
        'name' => 'required|string|max:255',
        'externalId' => 'nullable|string|max:255',
        'selectedGroupIds' => 'array',
        'selectedMemberIds' => 'array',
    ]);

    $this->loading = true;
    try {
        // Baue groups Array mit den richtigen Strukturen
        $groups = [];
        foreach ($this->selectedGroupIds as $groupId) {
            if (! empty($groupId)) {
                $groups[] = [
                    'id' => $groupId,
                    'readOnly' => false,
                    'hidePasswords' => false,
                    'manage' => false,
                ];
            }
        }

        // Baue users Array mit den richtigen Strukturen
        $users = [];
        foreach ($this->selectedMemberIds as $memberId) {
            if (! empty($memberId)) {
                $users[] = [
                    'id' => $memberId,
                    'readOnly' => false,
                    'hidePasswords' => false,
                    'manage' => false,
                ];
            }
        }

        // Stelle sicher, dass externalId immer auf den Namen gesetzt ist (wenn sich der Name geändert hat)
        if ($this->name !== $this->originalName) {
            $this->externalId = $this->name;
        }

        $data = [
            'name' => $this->name,
            'groups' => $groups,
            'users' => $users,
        ];

        // Verwende immer den Namen als externalId
        $data['externalId'] = $this->name;

        $this->vaultApiService()->updateCollection($this->collectionId, $data);

        Flux::toast('Collection erfolgreich aktualisiert', variant: 'success');
        $this->dispatch('collection-updated');
        return redirect()->route('apps.bitwarden.admin.collections.index');
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Aktualisieren der Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Collection bearbeiten" subheading="Name, Gruppen und Mitglieder dieser Collection." icon="folder">
    @if($loading)
        <x-intranet-app-bitwarden::section icon="folder" title="Collection" description="Die Collection wird geladen.">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="size-8 text-[#073070] dark:text-white" />
            </div>
        </x-intranet-app-bitwarden::section>
    @else
        <x-intranet-app-bitwarden::section icon="folder" title="Collection" description="Änderungen gelten nach dem Speichern.">
            <form wire:submit="save">
                <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                    <x-intranet-app-bitwarden::row label="Name" description="Wenn der Name geändert wird, wird die External ID automatisch aktualisiert." wide>
                        <flux:input wire:model.live="name" placeholder="Collection-Name" required />
                        <flux:error name="name" />
                    </x-intranet-app-bitwarden::row>
                    <x-intranet-app-bitwarden::row label="External ID" description="Wird automatisch aus dem Namen gesetzt, wenn dieser geändert wird." wide>
                        <flux:input wire:model="externalId" placeholder="Externe ID (wird automatisch aus Name gesetzt)" readonly />
                        <flux:error name="externalId" />
                    </x-intranet-app-bitwarden::row>
                    <x-intranet-app-bitwarden::row label="Gruppen" description="Wählen Sie die Gruppen aus, die Zugriff auf diese Collection haben sollen." wide>
                        @if(!empty($this->allGroups))
                            <flux:select
                                wire:model="selectedGroupIds"
                                variant="listbox"
                                multiple
                                placeholder="Gruppen auswählen …"
                                selected-suffix="Gruppen ausgewählt"
                            >
                                @foreach($this->allGroups as $group)
                                    @if(!empty($group) && is_array($group))
                                        @php
                                            $groupId = $group['id'] ?? null;
                                            $groupName = $group['name'] ?? 'Unbekannt';
                                        @endphp
                                        @if(!empty($groupId))
                                            <flux:select.option value="{{ $groupId }}">
                                                {{ $groupName }}
                                            </flux:select.option>
                                        @endif
                                    @endif
                                @endforeach
                            </flux:select>
                        @else
                            <p class="text-sm text-slate-600 dark:text-white/70">Keine Gruppen verfügbar.</p>
                        @endif
                        <flux:error name="selectedGroupIds" />
                    </x-intranet-app-bitwarden::row>
                    <x-intranet-app-bitwarden::row label="Mitglieder" description="Wählen Sie die Mitglieder aus, die Zugriff auf diese Collection haben sollen." wide>
                        @if(!empty($this->allMembers))
                            <flux:select
                                wire:model="selectedMemberIds"
                                variant="listbox"
                                multiple
                                placeholder="Mitglieder auswählen …"
                                selected-suffix="Mitglieder ausgewählt"
                            >
                                @foreach($this->allMembers as $member)
                                    @if(!empty($member) && is_array($member))
                                        @php
                                            $memberId = $member['id'] ?? null;
                                            $memberName = trim((string) ($member['name'] ?? '')) !== ''
                                                ? $member['name']
                                                : ($member['email'] ?? 'Unbekannt');
                                            $memberEmail = $member['email'] ?? '';
                                            $displayName = $memberName;
                                            if (!empty($memberEmail)) {
                                                $displayName .= ' ('.$memberEmail.')';
                                            }
                                        @endphp
                                        @if(!empty($memberId))
                                            <flux:select.option value="{{ $memberId }}">
                                                {{ $displayName }}
                                            </flux:select.option>
                                        @endif
                                    @endif
                                @endforeach
                            </flux:select>
                        @else
                            <p class="text-sm text-slate-600 dark:text-white/70">Keine Mitglieder verfügbar.</p>
                        @endif
                        <flux:error name="selectedMemberIds" />
                    </x-intranet-app-bitwarden::row>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                    <flux:button href="{{ route('apps.bitwarden.admin.collections.index') }}" variant="ghost">
                        Abbrechen
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>Änderungen speichern</span>
                        <span wire:loading>Wird gespeichert …</span>
                    </flux:button>
                </div>
            </form>
        </x-intranet-app-bitwarden::section>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>
</div>