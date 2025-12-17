<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;
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

$apiService = computed(fn() => app(BitwardenPublicApiService::class));
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
<x-intranet-app-bitwarden::bitwarden-layout heading="Collection bearbeiten" subheading="Bitwarden Collections">
    @if($loading)
        <flux:card>
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        </flux:card>
    @else
        <flux:card>
            <form wire:submit="save" class="space-y-6">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model.live="name" placeholder="Collection-Name" required />
                    <flux:description>Wenn der Name geändert wird, wird die External ID automatisch aktualisiert.</flux:description>
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>External ID</flux:label>
                    <flux:input wire:model="externalId" placeholder="Externe ID (wird automatisch aus Name gesetzt)" readonly />
                    <flux:description>Wird automatisch aus dem Namen gesetzt, wenn dieser geändert wird.</flux:description>
                    <flux:error name="externalId" />
                </flux:field>

                <flux:field class="mt-4">
                    <flux:label>Gruppen</flux:label>
                    <flux:description>Wählen Sie die Gruppen aus, die Zugriff auf diese Collection haben sollen.</flux:description>
                    @if(!empty($this->allGroups))
                        <flux:select
                            wire:model="selectedGroupIds"
                            variant="listbox"
                            multiple
                            placeholder="Gruppen auswählen..."
                            selected-suffix="Gruppen ausgewählt"
                            class="mt-2">
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
                        <flux:callout variant="info" class="mt-2">
                            Keine Gruppen verfügbar.
                        </flux:callout>
                    @endif
                    <flux:error name="selectedGroupIds" />
                </flux:field>

                <flux:field class="mt-4">
                    <flux:label>Mitglieder</flux:label>
                    <flux:description>Wählen Sie die Mitglieder aus, die Zugriff auf diese Collection haben sollen.</flux:description>
                    @if(!empty($this->allMembers))
                        <flux:select
                            wire:model="selectedMemberIds"
                            variant="listbox"
                            multiple
                            placeholder="Mitglieder auswählen..."
                            selected-suffix="Mitglieder ausgewählt"
                            class="mt-2">
                            @foreach($this->allMembers as $member)
                                @if(!empty($member) && is_array($member))
                                    @php
                                        $memberId = $member['id'] ?? null;
                                        $memberName = $member['name'] ?? 'Unbekannt';
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
                        <flux:callout variant="info" class="mt-2">
                            Keine Mitglieder verfügbar.
                        </flux:callout>
                    @endif
                    <flux:error name="selectedMemberIds" />
                </flux:field>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <flux:button href="{{ route('apps.bitwarden.admin.collections.index') }}" variant="ghost">
                        Abbrechen
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>Änderungen speichern</span>
                        <span wire:loading>Wird gespeichert...</span>
                    </flux:button>
                </div>
            </form>
        </flux:card>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>

</div>