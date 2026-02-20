<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;

use function Livewire\Volt\{state, title, mount};

title('Bitwarden - Gruppe bearbeiten');

state([
    'groupId' => null,
    'name' => '',
    'accessAll' => false,
    'collections' => [],
    'users' => [],
    'selectedMemberIds' => [],
    'loading' => false,
    'groupUsers' => [],
    'allMembers' => [],
]);

$apiService = fn() => app(BitwardenPublicApiService::class);

mount(function (string $groupId) {
    $this->groupId = $groupId;
    $this->loading = true;
    try {
        $group = $this->apiService()->getGroup($groupId);

        if (empty($group)) {
            Flux::toast('Gruppe nicht gefunden', variant: 'danger');
            return redirect()->route('apps.bitwarden.admin.groups.index');
        }

        $this->name = $group['name'] ?? '';
        $this->accessAll = $group['accessAll'] ?? false;
        $this->collections = $group['collections'] ?? [];
        $this->users = $group['users'] ?? [];

        // Lade Gruppen-Mitglieder
        try {
            $this->groupUsers = $this->apiService()->getGroupUsers($groupId);
            // getGroupUsers gibt ein Array von Member-IDs zurück (nicht Objekte)
            // Stelle sicher, dass selectedMemberIds ein Array von Strings ist
            if (is_array($this->groupUsers)) {
                $this->selectedMemberIds = array_values(array_filter($this->groupUsers, fn($id) => ! empty($id) && is_string($id)));
            } else {
                $this->selectedMemberIds = [];
            }
            
            \Illuminate\Support\Facades\Log::debug('Initialized selectedMemberIds', [
                'groupUsers' => $this->groupUsers,
                'selectedMemberIds' => $this->selectedMemberIds,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error loading group users', [
                'error' => $e->getMessage(),
            ]);
            $this->groupUsers = [];
            $this->selectedMemberIds = [];
        }

        // Lade alle verfügbaren Mitglieder
        try {
            $membersResponse = $this->apiService()->getMembers();
            
            // Stelle sicher, dass wir ein Array haben
            if (is_array($membersResponse)) {
                // Prüfe, ob die Daten in einem verschachtelten Format sind
                if (isset($membersResponse['data']) && is_array($membersResponse['data'])) {
                    $this->allMembers = $membersResponse['data'];
                } elseif (isset($membersResponse['members']) && is_array($membersResponse['members'])) {
                    $this->allMembers = $membersResponse['members'];
                } else {
                    $this->allMembers = $membersResponse;
                }
            } elseif (is_object($membersResponse)) {
                if (method_exists($membersResponse, 'toArray')) {
                    $this->allMembers = $membersResponse->toArray();
                } else {
                    $this->allMembers = json_decode(json_encode($membersResponse), true) ?? [];
                }
            } else {
                $this->allMembers = [];
            }
            
            // Stelle sicher, dass members ein numerisch indiziertes Array ist
            if (! empty($this->allMembers) && is_array($this->allMembers)) {
                $this->allMembers = array_values($this->allMembers);
            }
            
            \Illuminate\Support\Facades\Log::debug('Loaded members for group edit', [
                'count' => count($this->allMembers),
                'first_member' => $this->allMembers[0] ?? null,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error loading members for group edit', [
                'error' => $e->getMessage(),
            ]);
            $this->allMembers = [];
        }
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Laden der Gruppe: '.$e->getMessage(), variant: 'danger');
        return redirect()->route('apps.bitwarden.admin.groups.index');
    } finally {
        $this->loading = false;
    }
});

$save = function () {
    $this->validate([
        'name' => 'required|string|max:255',
        'accessAll' => 'boolean',
        'collections' => 'array',
        'users' => 'array',
        'selectedMemberIds' => 'array',
    ]);

    $this->loading = true;
    try {
        \Illuminate\Support\Facades\Log::debug('Saving group', [
            'groupId' => $this->groupId,
            'selectedMemberIds' => $this->selectedMemberIds,
            'selectedMemberIds_type' => gettype($this->selectedMemberIds),
            'selectedMemberIds_count' => is_array($this->selectedMemberIds) ? count($this->selectedMemberIds) : 'N/A',
        ]);

        // Aktualisiere zuerst die Gruppe
        $this->apiService()->updateGroup($this->groupId, [
            'name' => $this->name,
            'accessAll' => $this->accessAll,
            'collections' => $this->collections,
            'users' => $this->users,
        ]);

        // Aktualisiere dann die Gruppen-Mitglieder
        // Stelle sicher, dass selectedMemberIds ein Array ist
        $memberIds = is_array($this->selectedMemberIds) ? $this->selectedMemberIds : [];
        // Entferne leere Werte und re-indexiere das Array
        $memberIds = array_values(array_filter($memberIds, fn($id) => ! empty($id)));
        
        \Illuminate\Support\Facades\Log::debug('Updating group users', [
            'groupId' => $this->groupId,
            'memberIds' => $memberIds,
            'memberIds_count' => count($memberIds),
        ]);
        
        $this->apiService()->updateGroupUsers($this->groupId, $memberIds);

        Flux::toast('Gruppe erfolgreich aktualisiert', variant: 'success');
        $this->dispatch('group-updated');
        return redirect()->route('apps.bitwarden.admin.groups.index');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Error saving group', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Aktualisieren der Gruppe: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>

<x-intranet-app-bitwarden::bitwarden-layout heading="Gruppe bearbeiten" subheading="Bitwarden Gruppen">
    @if($loading)
        <flux:card class="glass-card">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        </flux:card>
    @else
        <flux:card class="glass-card">
            <form wire:submit="save" class="space-y-6">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="Gruppenname" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="accessAll" label="Zugriff auf alle Collections" />
                    <flux:error name="accessAll" />
                </flux:field>

                <flux:field class="mt-4">
                    <flux:label>Mitglieder</flux:label>
                    <flux:description>Wählen Sie die Mitglieder aus, die zu dieser Gruppe gehören sollen.</flux:description>
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
                                        $memberId = $member['id'] ?? $member['userId'] ?? $member['memberId'] ?? null;
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
                        @if(config('app.debug'))
                            <div class="mt-2 text-xs text-gray-500">
                                Debug: {{ count($this->allMembers) }} Mitglieder geladen, 
                                {{ count($this->selectedMemberIds ?? []) }} ausgewählt
                            </div>
                        @endif
                    @else
                        <flux:callout variant="info" class="mt-2">
                            Keine Mitglieder verfügbar. Bitte laden Sie zuerst Mitglieder über die Mitglieder-Verwaltung.
                            @if(config('app.debug'))
                                <div class="mt-2 text-xs">
                                    Debug: allMembers count = {{ count($this->allMembers ?? []) }}
                                </div>
                            @endif
                        </flux:callout>
                    @endif
                    <flux:error name="selectedMemberIds" />
                </flux:field>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <flux:button href="{{ route('apps.bitwarden.admin.groups.index') }}" variant="ghost">
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

