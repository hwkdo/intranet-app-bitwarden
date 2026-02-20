<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;

use function Livewire\Volt\{state, title, mount};

title('Bitwarden - Gruppe anzeigen');

state([
    'groupId' => null,
    'group' => null,
    'groupUserIds' => [],
    'allMembers' => [],
    'loading' => false,
]);

$apiService = fn() => app(BitwardenPublicApiService::class);

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

<x-intranet-app-bitwarden::bitwarden-layout heading="Gruppe anzeigen" subheading="Bitwarden Gruppen">
    @if($loading)
        <flux:card class="glass-card">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        </flux:card>
    @elseif($group)
        <flux:card class="glass-card">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $group['name'] ?? 'Unbenannt' }}</flux:heading>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <flux:label>Zugriff auf alle Collections</flux:label>
                        <div class="mt-1">
                            @if($group['accessAll'] ?? false)
                                <flux:badge variant="success" icon="check">Ja</flux:badge>
                            @else
                                <flux:badge variant="neutral">Nein</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div>
                        <flux:label>Anzahl Mitglieder</flux:label>
                        <div class="mt-1">
                            <flux:text size="lg">{{ count($groupUserIds) }}</flux:text>
                        </div>
                    </div>
                </div>

                @if(!empty($groupUserIds))
                    <div>
                        <flux:heading size="md" class="mb-4">Mitglieder</flux:heading>
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
                                        <flux:table.cell>{{ $member['name'] ?? 'Unbekannt' }}</flux:table.cell>
                                        <flux:table.cell>{{ $member['email'] ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge variant="neutral">{{ $member['type'] ?? 'User' }}</flux:badge>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <flux:button href="{{ route('apps.bitwarden.admin.groups.index') }}" variant="ghost" wire:navigate>
                        Zurück
                    </flux:button>
                    <flux:button href="{{ route('apps.bitwarden.admin.groups.edit', $groupId) }}" variant="primary" wire:navigate>
                        Bearbeiten
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>

