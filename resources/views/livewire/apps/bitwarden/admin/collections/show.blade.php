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

<x-intranet-app-bitwarden::bitwarden-layout heading="Collection anzeigen" subheading="Bitwarden Collections">
    @if($loading)
        <flux:card>
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        </flux:card>
    @elseif($collection)
        <flux:card>
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $collection['name'] ?? 'Unbenannt' }}</flux:heading>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <flux:label>External ID</flux:label>
                        <div class="mt-1">
                            <flux:text>{{ $collection['externalId'] ?? '-' }}</flux:text>
                        </div>
                    </div>

                    <div>
                        <flux:label>Anzahl Gruppen</flux:label>
                        <div class="mt-1">
                            <flux:text size="lg">{{ count($collection['groups'] ?? []) }}</flux:text>
                        </div>
                    </div>

                    <div>
                        <flux:label>Anzahl Benutzer</flux:label>
                        <div class="mt-1">
                            <flux:text size="lg">{{ count($collection['users'] ?? []) }}</flux:text>
                        </div>
                    </div>
                </div>

                @if(!empty($collection['groups']))
                    <div>
                        <flux:heading size="md" class="mb-4">Gruppen</flux:heading>
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
                    </div>
                @endif

                @if(!empty($collection['users']))
                    <div>
                        <flux:heading size="md" class="mb-4">Benutzer</flux:heading>
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
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <flux:button href="{{ route('apps.bitwarden.admin.collections.index') }}" variant="ghost" wire:navigate>
                        Zurück
                    </flux:button>
                    <flux:button href="{{ route('apps.bitwarden.admin.collections.edit', $collectionId) }}" variant="primary" wire:navigate>
                        Bearbeiten
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>

