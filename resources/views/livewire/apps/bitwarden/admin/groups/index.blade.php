<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;

use function Livewire\Volt\{state, title, computed, on, mount};

title('Bitwarden - Gruppen verwalten');

state([
    'groups' => [],
    'loading' => false,
    'search' => '',
]);

$apiService = computed(fn() => app(BitwardenPublicApiService::class));

$loadGroups = function () {
    $this->loading = true;
    try {
        $response = $this->apiService()->getGroups();
        
        // Stelle sicher, dass wir ein Array haben
        if (is_array($response)) {
            // Prüfe, ob die Daten in einem verschachtelten Format sind (z.B. ['data' => [...]])
            if (isset($response['data']) && is_array($response['data'])) {
                $this->groups = $response['data'];
            } elseif (isset($response['groups']) && is_array($response['groups'])) {
                $this->groups = $response['groups'];
            } else {
                $this->groups = $response;
            }
        } elseif (is_object($response)) {
            // Konvertiere Objekte zu Arrays
            if (method_exists($response, 'toArray')) {
                $this->groups = $response->toArray();
            } else {
                $this->groups = json_decode(json_encode($response), true) ?? [];
            }
        } else {
            $this->groups = [];
        }
        
        // Stelle sicher, dass groups ein numerisch indiziertes Array ist
        if (! empty($this->groups) && is_array($this->groups)) {
            $this->groups = array_values($this->groups);
        }
        
        // Lade für jede Gruppe die Mitglieder, um die Anzahl zu erhalten
        foreach ($this->groups as &$group) {
            if (isset($group['id'])) {
                try {
                    $users = $this->apiService()->getGroupUsers($group['id']);
                    $group['users'] = is_array($users) ? $users : [];
                    $group['userCount'] = count($group['users']);
                } catch (\Exception $e) {
                    $group['users'] = [];
                    $group['userCount'] = 0;
                }
            } else {
                $group['users'] = [];
                $group['userCount'] = 0;
            }
        }
        unset($group); // Wichtig: Referenz aufheben
        
        // Log für Debugging
        \Illuminate\Support\Facades\Log::debug('Bitwarden Groups loaded', [
            'count' => count($this->groups),
            'is_array' => is_array($this->groups),
            'raw_response_type' => gettype($response),
            'raw_response' => $response,
            'first_group' => $this->groups[0] ?? null,
            'first_group_keys' => ! empty($this->groups) ? array_keys($this->groups[0] ?? []) : [],
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Error loading Bitwarden groups', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Laden der Gruppen: '.$e->getMessage(), variant: 'danger');
        $this->groups = [];
    } finally {
        $this->loading = false;
    }
};

$deleteGroup = function (string $groupId) {
    if (! confirm('Möchten Sie diese Gruppe wirklich löschen?')) {
        return;
    }

    $this->loading = true;
    try {
        $this->apiService()->deleteGroup($groupId);
        Flux::toast('Gruppe erfolgreich gelöscht', variant: 'success');
        $this->loadGroups();
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Löschen der Gruppe: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$filteredGroups = computed(function () {
    if (empty($this->groups) || ! is_array($this->groups)) {
        return [];
    }

    // Filtere null-Werte heraus
    $groups = array_filter($this->groups, fn($group) => ! empty($group) && is_array($group));

    if (empty($groups)) {
        return [];
    }

    if (empty($this->search)) {
        return array_values($groups);
    }

    $search = strtolower($this->search);
    
    $filtered = array_filter($groups, function ($group) use ($search) {
        if (empty($group) || ! is_array($group)) {
            return false;
        }
        
        return str_contains(strtolower($group['name'] ?? ''), $search);
    });
    
    return array_values($filtered);
});

on(['group-created', 'group-updated' => function () {
    $this->loadGroups();
}]);

mount(function () {
    $this->loadGroups();
});

?>

<x-intranet-app-bitwarden::bitwarden-layout heading="Gruppen verwalten" subheading="Bitwarden Gruppen">
    <flux:card>
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">Gruppen</flux:heading>
            <flux:button href="{{ route('apps.bitwarden.admin.groups.create') }}" variant="primary" icon="plus">
                Neue Gruppe
            </flux:button>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Gruppen durchsuchen..."
            icon="magnifying-glass"
            class="mb-6"
        />

        @if(config('app.debug') && !empty($this->groups))
            <flux:callout variant="info" class="mb-4">
                <div class="text-xs">
                    <strong>Debug Info:</strong><br>
                    Groups Count: {{ count($this->groups) }}<br>
                    Filtered Count: {{ count($this->filteredGroups ?? []) }}<br>
                    First Group: {{ json_encode($this->groups[0] ?? null, JSON_PRETTY_PRINT) }}
                </div>
            </flux:callout>
        @endif

        @if($loading && empty($this->groups))
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        @elseif(empty($this->filteredGroups))
            <flux:callout variant="info" icon="information-circle">
                @if(empty($search))
                    Keine Gruppen gefunden.
                    @if(config('app.debug'))
                        <div class="mt-2 text-xs">
                            Debug: groups count = {{ count($this->groups ?? []) }}, 
                            filtered count = {{ count($this->filteredGroups ?? []) }},
                            raw response type = {{ gettype($this->groups ?? []) }}
                        </div>
                    @endif
                @else
                    Keine Gruppen gefunden, die "{{ $search }}" enthalten.
                @endif
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Zugriff auf alle</flux:table.column>
                    <flux:table.column>Mitglieder</flux:table.column>
                    <flux:table.column align="end">Aktionen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->filteredGroups as $group)
                        @php
                            // Versuche verschiedene ID-Felder
                            $groupId = $group['id'] ?? $group['groupId'] ?? null;
                        @endphp
                        @if(!empty($group) && !empty($groupId))
                        <flux:table.row wire:key="group-{{ $groupId }}">
                            <flux:table.cell>
                                <flux:heading size="sm">{{ $group['name'] ?? 'Unbenannt' }}</flux:heading>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($group['accessAll'] ?? false)
                                    <flux:badge variant="success" icon="check">Ja</flux:badge>
                                @else
                                    <flux:badge variant="neutral">Nein</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $group['userCount'] ?? count($group['users'] ?? []) }} Mitglieder
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button
                                        href="{{ route('apps.bitwarden.admin.groups.show', ['groupId' => $groupId]) }}"
                                        variant="ghost"
                                        icon="eye"
                                        size="sm"
                                        wire:navigate
                                    >
                                        Anzeigen
                                    </flux:button>
                                    <flux:button
                                        href="{{ route('apps.bitwarden.admin.groups.edit', ['groupId' => $groupId]) }}"
                                        variant="ghost"
                                        icon="pencil"
                                        size="sm"
                                        wire:navigate
                                    >
                                        Bearbeiten
                                    </flux:button>
                                    <flux:button
                                        wire:click="deleteGroup('{{ $groupId }}')"
                                        wire:confirm="Möchten Sie diese Gruppe wirklich löschen?"
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

