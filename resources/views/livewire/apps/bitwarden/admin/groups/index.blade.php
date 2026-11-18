<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;

use function Livewire\Volt\{state, title, computed, on, mount};

title('Bitwarden - Gruppen verwalten');

state([
    'groups' => [],
    'loading' => false,
    'search' => '',
]);

$apiService = computed(fn() => app(BitwardenManagementApiInterface::class));

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
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Gruppen" subheading="Bitwarden-Gruppen anlegen, prüfen und entfernen." icon="user-group">
    <div class="glass-card sticky top-3 z-20 p-3" style="--glass-opacity: 0.92">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Gruppen durchsuchen …"
                    icon="magnifying-glass"
                    aria-label="Gruppen durchsuchen"
                    clearable
                />
            </div>
            <flux:button href="{{ route('apps.bitwarden.admin.groups.create') }}" variant="primary" icon="plus">
                Neue Gruppe
            </flux:button>
        </div>
    </div>

    @if(config('app.debug') && !empty($this->groups))
        <x-intranet-app-bitwarden::note icon="information-circle" title="Debug">
            Gruppen: {{ count($this->groups) }} · Gefiltert: {{ count($this->filteredGroups ?? []) }}
        </x-intranet-app-bitwarden::note>
    @endif

    @if($loading && empty($this->groups))
        <x-intranet-app-bitwarden::section icon="user-group" title="Gruppen" description="Die Gruppen werden geladen.">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="size-8 text-[#073070] dark:text-white" />
            </div>
        </x-intranet-app-bitwarden::section>
    @elseif(empty($this->filteredGroups))
        <x-intranet-app-bitwarden::empty icon="user-group" title="Keine Gruppen">
            @if(empty($search))
                Keine Gruppen gefunden.
                @if(config('app.debug'))
                    <span class="mt-2 block text-xs">
                        Debug: groups count = {{ count($this->groups ?? []) }},
                        filtered count = {{ count($this->filteredGroups ?? []) }},
                        raw response type = {{ gettype($this->groups ?? []) }}
                    </span>
                @endif
            @else
                Keine Gruppen gefunden, die „{{ $search }}“ enthalten.
            @endif
        </x-intranet-app-bitwarden::empty>
    @else
        <x-intranet-app-bitwarden::section icon="user-group" title="Gruppen" description="{{ count($this->filteredGroups) }} {{ count($this->filteredGroups) === 1 ? 'Gruppe' : 'Gruppen' }}">
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
        </x-intranet-app-bitwarden::section>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
