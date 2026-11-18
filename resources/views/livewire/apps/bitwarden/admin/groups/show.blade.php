<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;

use function Livewire\Volt\{state, title, mount};

title('Bitwarden - Gruppe anzeigen');

state([
    'groupId' => null,
    'group' => null,
    'groupUserIds' => [],
    'allMembers' => [],
    'loading' => false,
]);

$apiService = fn() => app(BitwardenManagementApiInterface::class);

mount(function (string $groupId) {
    $this->groupId = $groupId;
    $this->loading = true;
    try {
        $this->group = $this->apiService()->getGroup($groupId);

        if (empty($this->group)) {
            Flux::toast('Gruppe nicht gefunden', variant: 'danger');
            return;
        }

        // Lade Gruppen-Mitglieder-IDs
        try {
            $userIds = $this->apiService()->getGroupUsers($groupId);
            // getGroupUsers gibt ein Array von Member-IDs zurück (nicht Objekte)
            $this->groupUserIds = is_array($userIds) ? array_values(array_filter($userIds, fn($id) => ! empty($id) && is_string($id))) : [];
        } catch (\Exception $e) {
            $this->groupUserIds = [];
        }

        // Lade alle Mitglieder, um Namen und E-Mails zu bekommen
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
            // Konvertiere zu einem assoziativen Array mit ID als Key
            if (! empty($this->allMembers) && is_array($this->allMembers)) {
                $membersById = [];
                foreach ($this->allMembers as $member) {
                    if (isset($member['id'])) {
                        $membersById[$member['id']] = $member;
                    }
                }
                $this->allMembers = $membersById;
            }
        } catch (\Exception $e) {
            $this->allMembers = [];
        }
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Laden der Gruppe: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
});

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Gruppe" subheading="Details der Bitwarden-Gruppe." icon="user-group">
    @if($loading)
        <x-intranet-app-bitwarden::section icon="user-group" title="Gruppe" description="Die Gruppe wird geladen.">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="size-8 text-[#073070] dark:text-white" />
            </div>
        </x-intranet-app-bitwarden::section>
    @elseif($group)
        <x-intranet-app-bitwarden::section icon="user-group" :title="$group['name'] ?? 'Unbenannt'" description="Zugriff und Mitglieder dieser Gruppe.">
            <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                <x-intranet-app-bitwarden::row label="Zugriff auf alle Collections">
                    @if($group['accessAll'] ?? false)
                        <flux:badge variant="success" icon="check">Ja</flux:badge>
                    @else
                        <flux:badge variant="neutral">Nein</flux:badge>
                    @endif
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Anzahl Mitglieder">
                    {{ count($groupUserIds) }}
                </x-intranet-app-bitwarden::row>
            </div>
        </x-intranet-app-bitwarden::section>

        @if(!empty($groupUserIds))
            <x-intranet-app-bitwarden::section icon="users" title="Mitglieder" description="{{ count($groupUserIds) }} {{ count($groupUserIds) === 1 ? 'Mitglied' : 'Mitglieder' }}">
                <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Name</flux:table.column>
                                <flux:table.column>E-Mail</flux:table.column>
                                <flux:table.column>Typ</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($groupUserIds as $userId)
                                    @php
                                        $member = $allMembers[$userId] ?? null;
                                    @endphp
                                    <flux:table.row wire:key="user-{{ $userId }}">
                                        <flux:table.cell>{{ trim((string) ($member['name'] ?? '')) !== '' ? $member['name'] : ($member['email'] ?? 'Unbekannt') }}</flux:table.cell>
                                        <flux:table.cell>{{ $member['email'] ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge variant="neutral">{{ $member['type'] ?? 'User' }}</flux:badge>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                </flux:table>
            </x-intranet-app-bitwarden::section>
        @endif

        <div class="flex items-center justify-end gap-3">
            <flux:button href="{{ route('apps.bitwarden.admin.groups.index') }}" variant="ghost" wire:navigate>
                Zurück
            </flux:button>
            <flux:button href="{{ route('apps.bitwarden.admin.groups.edit', $groupId) }}" variant="primary" wire:navigate>
                Bearbeiten
            </flux:button>
        </div>
    @else
        <x-intranet-app-bitwarden::empty icon="user-group" title="Gruppe nicht gefunden">
            Die angeforderte Gruppe ist nicht vorhanden.
        </x-intranet-app-bitwarden::empty>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
