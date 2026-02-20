<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;

use function Livewire\Volt\{state, title, computed, mount};

title('Bitwarden - Mitglied bearbeiten');

state([
    'memberId' => null,
    'member' => null,
    'type' => '2',
    'accessAll' => false,
    'collections' => [],
    'groups' => [],
    'loading' => false,
]);

$apiService = computed(fn() => app(BitwardenPublicApiService::class));
$availableGroups = computed(function () {
    try {
        return $this->apiService()->getGroups();
    } catch (\Exception $e) {
        return [];
    }
});

mount(function (string $memberId) {
    $this->memberId = $memberId;
    $this->loading = true;
    try {
        $this->member = $this->apiService()->getMember(
            $memberId,
            includeCollections: true,
            includeGroups: true
        );

        if (empty($this->member)) {
            Flux::toast('Mitglied nicht gefunden', variant: 'danger');
            return redirect()->route('apps.bitwarden.admin.members.index');
        }

        $this->type = $this->member['type'] ?? '2';
        $this->accessAll = $this->member['accessAll'] ?? false;
        $this->collections = $this->member['collections'] ?? [];
        
        // Extrahiere Gruppen-IDs - prüfe verschiedene mögliche Strukturen
        $groups = $this->member['groups'] ?? [];
        if (is_array($groups)) {
            $this->groups = collect($groups)->map(function ($group) {
                return is_array($group) ? ($group['id'] ?? $group['groupId'] ?? null) : $group;
            })->filter()->values()->toArray();
        } else {
            $this->groups = [];
        }
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Laden des Mitglieds: '.$e->getMessage(), variant: 'danger');
        return redirect()->route('apps.bitwarden.admin.members.index');
    } finally {
        $this->loading = false;
    }
});

$save = function () {
    $this->validate([
        'type' => 'required|string|in:0,1,2,3,4,Owner,Admin,User,Manager,Custom',
        'accessAll' => 'boolean',
        'collections' => 'array',
        'groups' => 'array',
    ]);

    $this->loading = true;
    try {
        $this->apiService()->updateMember($this->memberId, [
            'type' => $this->type,
            'accessAll' => $this->accessAll,
            'collections' => $this->collections,
            'groups' => $this->groups,
        ]);

        Flux::toast('Mitglied erfolgreich aktualisiert', variant: 'success');
        $this->dispatch('member-updated');
        return redirect()->route('apps.bitwarden.admin.members.index');
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Aktualisieren des Mitglieds: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>

<x-intranet-app-bitwarden::bitwarden-layout heading="Mitglied bearbeiten" subheading="Bitwarden Mitglieder">
    @if($loading)
        <flux:card class="glass-card">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        </flux:card>
    @elseif($member)
        <flux:card class="glass-card">
            <div class="mb-6">
                <flux:heading size="lg">{{ $member['name'] ?? 'Unbekannt' }}</flux:heading>
                <flux:text class="text-gray-600">{{ $member['email'] ?? '-' }}</flux:text>
            </div>

            <form wire:submit="save" class="space-y-6">
                <flux:field>
                    <flux:label>Typ</flux:label>
                    <flux:select wire:model="type" required>
                        <option value="0">Owner</option>
                        <option value="1">Admin</option>
                        <option value="2">User</option>
                        <option value="3">Manager</option>
                        <option value="4">Custom</option>
                    </flux:select>
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="accessAll" label="Zugriff auf alle Collections" />
                    <flux:error name="accessAll" />
                </flux:field>

                @if(!empty($availableGroups))
                    <flux:field>
                        <flux:label>Gruppen</flux:label>
                        <flux:select wire:model="groups" multiple>
                            @foreach($availableGroups as $group)
                                <option value="{{ $group['id'] }}">{{ $group['name'] ?? 'Unbenannt' }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="groups" />
                    </flux:field>
                @endif

                <div class="flex items-center justify-end gap-3 mt-6">
                    <flux:button href="{{ route('apps.bitwarden.admin.members.index') }}" variant="ghost">
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

