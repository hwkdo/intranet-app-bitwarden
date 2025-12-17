<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;

use function Livewire\Volt\{state, title, computed, mount};

title('Bitwarden - Collection erstellen');

state([
    'name' => '',
    'externalId' => '',
    'groups' => [],
    'users' => [],
    'loading' => false,
    'allGroups' => [],
    'allMembers' => [],
    'selectedGroupIds' => [],
    'selectedMemberIds' => [],
]);

$apiService = computed(fn() => app(BitwardenPublicApiService::class));
$vaultApiService = computed(fn() => app(BitwardenVaultApiService::class));

// Wenn der Name geändert wird, setze externalId automatisch auf den Namen
$updatedName = function ($value) {
    $this->externalId = $value;
};

mount(function () {
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

        // Stelle sicher, dass externalId immer auf den Namen gesetzt ist
        $this->externalId = $this->name;

        $data = [
            'name' => $this->name,
            'externalId' => $this->name,
            'groups' => $groups,
            'users' => $users,
        ];

        // Verwende die Vault API zum Erstellen der Collection
        $this->vaultApiService()->createCollection($data);

        Flux::toast('Collection erfolgreich erstellt', variant: 'success');
        $this->dispatch('collection-created');
        return redirect()->route('apps.bitwarden.admin.collections.index');
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Erstellen der Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Neue Collection erstellen" subheading="Bitwarden Collections">
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model.live="name" placeholder="Collection-Name" required />
                <flux:description>Der Name wird automatisch als External ID gespeichert.</flux:description>
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>External ID</flux:label>
                <flux:input wire:model="externalId" placeholder="Externe ID (wird automatisch aus Name gesetzt)" readonly />
                <flux:description>Wird automatisch aus dem Namen gesetzt.</flux:description>
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
                    <span wire:loading.remove>Collection erstellen</span>
                    <span wire:loading>Wird erstellt...</span>
                </flux:button>
            </div>
        </form>
    </flux:card>
</x-intranet-app-bitwarden::bitwarden-layout>

</div>