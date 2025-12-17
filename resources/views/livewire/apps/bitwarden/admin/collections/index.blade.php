<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;

use function Livewire\Volt\{state, title, computed, on, mount};

title('Bitwarden - Collections verwalten');

state([
    'collections' => [],
    'loading' => false,
    'search' => '',
]);

$vaultApiService = computed(fn() => app(BitwardenVaultApiService::class));

$loadCollections = function () {
    $this->loading = true;
    try {
        $response = $this->vaultApiService()->listOrgCollections();

        // Stelle sicher, dass wir ein Array haben
        if (is_array($response)) {
            // Prüfe, ob die Daten in einem verschachtelten Format sind
            // Bitwarden API Format: ['success' => true, 'data' => ['object' => 'list', 'data' => [...]]]
            if (isset($response['data']['data']) && is_array($response['data']['data'])) {
                $this->collections = $response['data']['data'];
            } elseif (isset($response['data']) && is_array($response['data'])) {
                // Falls data direkt ein Array ist
                $this->collections = $response['data'];
            } elseif (isset($response['collections']) && is_array($response['collections'])) {
                $this->collections = $response['collections'];
            } else {
                $this->collections = $response;
            }
        } elseif (is_object($response)) {
            // Konvertiere Objekte zu Arrays
            $responseArray = json_decode(json_encode($response), true) ?? [];
            
            // Prüfe verschachtelte Struktur
            if (isset($responseArray['data']['data']) && is_array($responseArray['data']['data'])) {
                $this->collections = $responseArray['data']['data'];
            } elseif (isset($responseArray['data']) && is_array($responseArray['data'])) {
                $this->collections = $responseArray['data'];
            } else {
                $this->collections = $responseArray;
            }
        } else {
            $this->collections = [];
        }

        // Stelle sicher, dass collections ein numerisch indiziertes Array ist
        if (! empty($this->collections) && is_array($this->collections)) {
            // Filtere String-Einträge heraus (falls die API z.B. ["list", {...}, {...}] zurückgibt)
            $this->collections = array_filter($this->collections, function ($item) {
                return is_array($item) || is_object($item);
            });
            $this->collections = array_values($this->collections);
            
            // Konvertiere Objekte zu Arrays
            $this->collections = array_map(function ($item) {
                if (is_object($item)) {
                    return json_decode(json_encode($item), true);
                }
                return $item;
            }, $this->collections);
            
            // Lade Details für jede Collection, um groups und users zu bekommen
            $this->collections = array_map(function ($collection) {
                if (empty($collection['id'])) {
                    return $collection;
                }
                
                // Wenn groups oder users nicht vorhanden sind, lade die Details
                if (!isset($collection['groups']) || !isset($collection['users'])) {
                    try {
                        $details = $this->vaultApiService()->getCollection($collection['id']);
                        
                        // Extrahiere die Collection aus der verschachtelten Struktur
                        $collectionDetails = $details;
                        if (isset($details['data']) && is_array($details['data'])) {
                            if (isset($details['data']['object']) && $details['data']['object'] === 'org-collection') {
                                $collectionDetails = $details['data'];
                            } else {
                                $collectionDetails = $details['data'];
                            }
                        }
                        
                        // Füge groups und users zur Collection hinzu
                        if (isset($collectionDetails['groups'])) {
                            $collection['groups'] = $collectionDetails['groups'];
                        }
                        if (isset($collectionDetails['users'])) {
                            $collection['users'] = $collectionDetails['users'];
                        }
                    } catch (\Exception $e) {
                        // Fehler beim Laden der Details ignorieren
                        \Illuminate\Support\Facades\Log::debug('Error loading collection details', [
                            'collectionId' => $collection['id'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                return $collection;
            }, $this->collections);
        }

        // Log für Debugging
        \Illuminate\Support\Facades\Log::debug('Bitwarden Collections loaded', [
            'count' => count($this->collections),
            'is_array' => is_array($this->collections),
            'raw_response' => $response,
            'collections' => $this->collections,
            'first_collection' => $this->collections[0] ?? null,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Error loading Bitwarden collections', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Laden der Collections: '.$e->getMessage(), variant: 'danger');
        $this->collections = [];
    } finally {
        $this->loading = false;
    }
};

$deleteCollection = function (string $collectionId) {
    if (! confirm('Möchten Sie diese Collection wirklich löschen?')) {
        return;
    }

    $this->loading = true;
    try {
        $this->vaultApiService()->deleteCollection($collectionId);
        Flux::toast('Collection erfolgreich gelöscht', variant: 'success');
        $this->loadCollections();
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Löschen der Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$filteredCollections = computed(function () {
    if (empty($this->collections) || ! is_array($this->collections)) {
        return [];
    }

    // Filtere null-Werte und Strings heraus, akzeptiere Arrays und Objekte
    $collections = array_filter($this->collections, function ($collection) {
        if (empty($collection)) {
            return false;
        }
        // Akzeptiere Arrays und Objekte
        return is_array($collection) || is_object($collection);
    });

    // Konvertiere Objekte zu Arrays
    $collections = array_map(function ($collection) {
        if (is_object($collection)) {
            return json_decode(json_encode($collection), true);
        }
        return $collection;
    }, $collections);

    if (empty($collections)) {
        return [];
    }

    if (empty($this->search)) {
        return array_values($collections);
    }

    $search = strtolower($this->search);

    $filtered = array_filter($collections, function ($collection) use ($search) {
        if (empty($collection) || ! is_array($collection)) {
            return false;
        }

        return str_contains(strtolower($collection['name'] ?? ''), $search);
    });

    return array_values($filtered);
});

on(['collection-created', 'collection-updated' => function () {
    $this->loadCollections();
}]);

mount(function () {
    $this->loadCollections();
});

?>

<x-intranet-app-bitwarden::bitwarden-layout heading="Collections verwalten" subheading="Bitwarden Collections">
    <flux:card>
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">Collections</flux:heading>
            <flux:button href="{{ route('apps.bitwarden.admin.collections.create') }}" variant="primary" icon="plus">
                Neue Collection
            </flux:button>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Collections durchsuchen..."
            icon="magnifying-glass"
            class="mb-6"
        />

        @if($loading && empty($this->collections))
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        @elseif(empty($this->filteredCollections))
            <flux:callout variant="info" icon="information-circle">
                @if(empty($search))
                    Keine Collections gefunden.
                @else
                    Keine Collections gefunden, die "{{ $search }}" enthalten.
                @endif
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>External ID</flux:table.column>
                    <flux:table.column>Gruppen</flux:table.column>
                    <flux:table.column>Benutzer</flux:table.column>
                    <flux:table.column align="end">Aktionen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->filteredCollections as $collection)
                        @php
                            // Versuche verschiedene ID-Felder
                            $collectionId = $collection['id'] ?? $collection['collectionId'] ?? null;
                        @endphp
                        @if(!empty($collection) && !empty($collectionId))
                        <flux:table.row wire:key="collection-{{ $collectionId }}">
                            <flux:table.cell>
                                <flux:heading size="sm">{{ $collection['name'] ?? 'Unbenannt' }}</flux:heading>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $collection['externalId'] ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $groups = $collection['groups'] ?? [];
                                    $groupsCount = is_array($groups) ? count($groups) : 0;
                                @endphp
                                {{ $groupsCount }} {{ $groupsCount === 1 ? 'Gruppe' : 'Gruppen' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $users = $collection['users'] ?? [];
                                    $usersCount = is_array($users) ? count($users) : 0;
                                @endphp
                                {{ $usersCount }} {{ $usersCount === 1 ? 'Benutzer' : 'Benutzer' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button
                                        href="{{ route('apps.bitwarden.admin.collections.show', ['collectionId' => $collectionId]) }}"
                                        variant="ghost"
                                        icon="eye"
                                        size="sm"
                                        wire:navigate
                                    >
                                        Anzeigen
                                    </flux:button>
                                    <flux:button
                                        href="{{ route('apps.bitwarden.admin.collections.edit', ['collectionId' => $collectionId]) }}"
                                        variant="ghost"
                                        icon="pencil"
                                        size="sm"
                                        wire:navigate
                                    >
                                        Bearbeiten
                                    </flux:button>
                                    <flux:button
                                        wire:click="deleteCollection('{{ $collectionId }}')"
                                        wire:confirm="Möchten Sie diese Collection wirklich löschen?"
                                        variant="ghost"
                                        icon="trash"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700"
                                    >
                                        Löschen
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                        @endif
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</x-intranet-app-bitwarden::bitwarden-layout>

