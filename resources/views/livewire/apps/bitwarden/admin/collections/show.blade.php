<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;

use function Livewire\Volt\{state, title, computed, mount};

title('Bitwarden - Collection anzeigen');

state([
    'collectionId' => null,
    'collection' => null,
    'loading' => false,
]);

$vaultApiService = computed(fn() => app(BitwardenVaultApiService::class));

mount(function (string $collectionId) {
    $this->collectionId = $collectionId;
    $this->loading = true;
    try {
        $this->collection = $this->vaultApiService()->getCollection($collectionId);

        if (empty($this->collection)) {
            Flux::toast('Collection nicht gefunden', variant: 'danger');
            return;
        }
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Laden der Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
});

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Collection" subheading="Details der Bitwarden-Collection." icon="folder">
    @if($loading)
        <x-intranet-app-bitwarden::section icon="folder" title="Collection" description="Die Collection wird geladen.">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="size-8 text-[#073070] dark:text-white" />
            </div>
        </x-intranet-app-bitwarden::section>
    @elseif($collection)
        <x-intranet-app-bitwarden::section icon="folder" :title="$collection['name'] ?? 'Unbenannt'" description="Zugriff und Zuordnung dieser Collection.">
            <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                <x-intranet-app-bitwarden::row label="External ID">
                    {{ $collection['externalId'] ?? '-' }}
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Anzahl Gruppen">
                    {{ count($collection['groups'] ?? []) }}
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Anzahl Benutzer">
                    {{ count($collection['users'] ?? []) }}
                </x-intranet-app-bitwarden::row>
            </div>
        </x-intranet-app-bitwarden::section>

        @if(!empty($collection['groups']))
            <x-intranet-app-bitwarden::section icon="user-group" title="Gruppen" description="Gruppen mit Zugriff auf diese Collection.">
                <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Gruppen-ID</flux:table.column>
                                <flux:table.column>Read Only</flux:table.column>
                                <flux:table.column>Hide Passwords</flux:table.column>
                                <flux:table.column>Manage</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($collection['groups'] as $group)
                                    @php
                                        $groupId = is_array($group) ? ($group['id'] ?? null) : $group;
                                    @endphp
                                    @if(!empty($groupId))
                                        <flux:table.row wire:key="group-{{ $groupId }}">
                                            <flux:table.cell>{{ $groupId }}</flux:table.cell>
                                            <flux:table.cell>
                                                @if(is_array($group) && ($group['readOnly'] ?? false))
                                                    <flux:badge variant="success" icon="check">Ja</flux:badge>
                                                @else
                                                    <flux:badge variant="neutral">Nein</flux:badge>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell>
                                                @if(is_array($group) && ($group['hidePasswords'] ?? false))
                                                    <flux:badge variant="success" icon="check">Ja</flux:badge>
                                                @else
                                                    <flux:badge variant="neutral">Nein</flux:badge>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell>
                                                @if(is_array($group) && ($group['manage'] ?? false))
                                                    <flux:badge variant="success" icon="check">Ja</flux:badge>
                                                @else
                                                    <flux:badge variant="neutral">Nein</flux:badge>
                                                @endif
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endif
                                @endforeach
                            </flux:table.rows>
                </flux:table>
            </x-intranet-app-bitwarden::section>
        @endif

        @if(!empty($collection['users']))
            <x-intranet-app-bitwarden::section icon="users" title="Benutzer" description="Benutzer mit Zugriff auf diese Collection.">
                <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Benutzer-ID</flux:table.column>
                                <flux:table.column>Read Only</flux:table.column>
                                <flux:table.column>Hide Passwords</flux:table.column>
                                <flux:table.column>Manage</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($collection['users'] as $user)
                                    @php
                                        $userId = is_array($user) ? ($user['id'] ?? null) : $user;
                                    @endphp
                                    @if(!empty($userId))
                                        <flux:table.row wire:key="user-{{ $userId }}">
                                            <flux:table.cell>{{ $userId }}</flux:table.cell>
                                            <flux:table.cell>
                                                @if(is_array($user) && ($user['readOnly'] ?? false))
                                                    <flux:badge variant="success" icon="check">Ja</flux:badge>
                                                @else
                                                    <flux:badge variant="neutral">Nein</flux:badge>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell>
                                                @if(is_array($user) && ($user['hidePasswords'] ?? false))
                                                    <flux:badge variant="success" icon="check">Ja</flux:badge>
                                                @else
                                                    <flux:badge variant="neutral">Nein</flux:badge>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell>
                                                @if(is_array($user) && ($user['manage'] ?? false))
                                                    <flux:badge variant="success" icon="check">Ja</flux:badge>
                                                @else
                                                    <flux:badge variant="neutral">Nein</flux:badge>
                                                @endif
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endif
                                @endforeach
                            </flux:table.rows>
                </flux:table>
            </x-intranet-app-bitwarden::section>
        @endif

        <div class="flex items-center justify-end gap-3">
            <flux:button href="{{ route('apps.bitwarden.admin.collections.index') }}" variant="ghost" wire:navigate>
                Zurück
            </flux:button>
            <flux:button href="{{ route('apps.bitwarden.admin.collections.edit', $collectionId) }}" variant="primary" wire:navigate>
                Bearbeiten
            </flux:button>
        </div>
    @else
        <x-intranet-app-bitwarden::empty icon="folder" title="Collection nicht gefunden">
            Die angeforderte Collection ist nicht vorhanden.
        </x-intranet-app-bitwarden::empty>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
