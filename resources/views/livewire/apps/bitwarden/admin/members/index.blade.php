<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Hwkdo\BitwardenLaravel\Support\OrganizationMemberStatus;
use Illuminate\Pagination\LengthAwarePaginator;

use function Livewire\Volt\{state, title, computed, on, mount, usesPagination};

title('Bitwarden - Mitglieder verwalten');

usesPagination();

state([
    'members' => [],
    'loading' => false,
    'search' => '',
    'includeCollections' => false,
    'includeGroups' => false,
    'onlyCompleteUsers' => true,
    'onlyRecoveryEnrolled' => false,
    'onlyNeedsConfirm' => false,
]);

$apiService = computed(fn () => app(BitwardenManagementApiInterface::class));

$vaultApiService = computed(fn () => app(BitwardenVaultApiService::class));

$loadMembers = function () {
    $this->loading = true;
    try {
        $response = $this->apiService()->getMembers(
            includeCollections: $this->includeCollections,
            includeGroups: $this->includeGroups
        );

        // Stelle sicher, dass wir ein Array haben
        if (is_array($response)) {
            // Prüfe, ob die Daten in einem verschachtelten Format sind (z.B. ['data' => [...]])
            if (isset($response['data']) && is_array($response['data'])) {
                $this->members = $response['data'];
            } elseif (isset($response['members']) && is_array($response['members'])) {
                $this->members = $response['members'];
            } else {
                $this->members = $response;
            }
        } elseif (is_object($response)) {
            // Konvertiere Objekte zu Arrays
            if (method_exists($response, 'toArray')) {
                $this->members = $response->toArray();
            } else {
                $this->members = json_decode(json_encode($response), true) ?? [];
            }
        } else {
            $this->members = [];
        }

        // Stelle sicher, dass members ein numerisch indiziertes Array ist
        if (! empty($this->members) && is_array($this->members)) {
            $this->members = array_values($this->members);
        }

        // Log für Debugging
        \Illuminate\Support\Facades\Log::debug('Bitwarden Members loaded', [
            'count' => count($this->members),
            'is_array' => is_array($this->members),
            'first_member_keys' => ! empty($this->members) ? array_keys($this->members[0] ?? []) : [],
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Error loading Bitwarden members', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Laden der Mitglieder: '.$e->getMessage(), variant: 'danger');
        $this->members = [];
    } finally {
        $this->loading = false;
    }
};

$deleteMember = function (string $memberId) {
    $this->loading = true;
    try {
        $this->apiService()->deleteMember($memberId);
        Flux::toast('Mitglied erfolgreich gelöscht', variant: 'success');
        $this->loadMembers();
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Löschen des Mitglieds: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$confirmMember = function (string $memberId) {
    $this->loading = true;
    try {
        $this->vaultApiService()->ensureUnlocked();
        $this->vaultApiService()->confirmMember($memberId);
        Flux::toast('Mitglied erfolgreich bestätigt', variant: 'success');
        $this->loadMembers();
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Bestätigen des Mitglieds: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$memberDisplayName = function (array $member): string {
    $name = trim((string) ($member['name'] ?? ''));

    if ($name !== '' && strcasecmp($name, 'Unbekannt') !== 0) {
        return $name;
    }

    $email = trim((string) ($member['email'] ?? ''));

    return $email !== '' ? $email : 'Unbekannt';
};

$memberStatus = function (array $member): int {
    return (int) ($member['status'] ?? -1);
};

$memberNeedsConfirm = function (array $member): bool {
    return OrganizationMemberStatus::needsConfirm($member);
};

$memberStatusLabel = function (array $member): string {
    return match ($this->memberStatus($member)) {
        OrganizationMemberStatus::INVITED => 'Eingeladen',
        OrganizationMemberStatus::ACCEPTED => 'Angenommen',
        OrganizationMemberStatus::CONFIRMED => 'Bestätigt',
        default => '–',
    };
};

$isCompleteMember = function (array $member): bool {
    // „Vollständig“ = bestätigt/angenommen (nicht nur eingeladen).
    // Status: 0=Invited, 1=Accepted, 2=Confirmed, …
    $status = $member['status'] ?? null;

    if ($status !== null && (int) $status === 0) {
        return false;
    }

    $name = trim((string) ($member['name'] ?? ''));
    $email = trim((string) ($member['email'] ?? ''));

    return ($name !== '' && strcasecmp($name, 'Unbekannt') !== 0) || $email !== '';
};

$isRecoveryEnrolled = function (array $member): bool {
    return (bool) ($member['resetPasswordEnrolled'] ?? $member['ResetPasswordEnrolled'] ?? false);
};

$filteredMembers = computed(function () {
    $members = [];

    if (! empty($this->members) && is_array($this->members)) {
        $members = array_values(array_filter(
            $this->members,
            fn ($member) => ! empty($member) && is_array($member)
        ));
    }

    if ($this->onlyCompleteUsers) {
        $members = array_values(array_filter(
            $members,
            fn (array $member): bool => $this->isCompleteMember($member)
        ));
    }

    if ($this->onlyRecoveryEnrolled) {
        $members = array_values(array_filter(
            $members,
            fn (array $member): bool => $this->isRecoveryEnrolled($member)
        ));
    }

    if ($this->onlyNeedsConfirm) {
        $members = array_values(array_filter(
            $members,
            fn (array $member): bool => $this->memberNeedsConfirm($member)
        ));
    }

    if (! empty($this->search)) {
        $search = strtolower($this->search);

        $members = array_values(array_filter($members, function ($member) use ($search) {
            $name = strtolower($member['name'] ?? '');
            $email = strtolower($member['email'] ?? '');

            return str_contains($name, $search) || str_contains($email, $search);
        }));
    }

    $perPage = 20;
    $page = $this->getPage();
    $items = collect($members);

    return new LengthAwarePaginator(
        $items->forPage($page, $perPage)->values(),
        $items->count(),
        $perPage,
        $page,
        [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ],
    );
});

on(['member-created', 'member-updated' => function () {
    $this->loadMembers();
}]);

$updatedIncludeCollections = function () {
    $this->loadMembers();
};

$updatedIncludeGroups = function () {
    $this->loadMembers();
};

$updatedSearch = function () {
    $this->resetPage();
};

$updatedOnlyCompleteUsers = function () {
    $this->resetPage();
};

$updatedOnlyRecoveryEnrolled = function () {
    $this->resetPage();
};

$updatedOnlyNeedsConfirm = function () {
    $this->resetPage();
};

mount(function () {
    $this->loadMembers();
});

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Mitglieder verwalten" subheading="Bitwarden Mitglieder">
    <flux:card class="glass-card">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">Mitglieder</flux:heading>
            <flux:button href="{{ route('apps.bitwarden.admin.members.invite') }}" variant="primary" icon="plus">
                Mitglied einladen
            </flux:button>
        </div>

        <div class="flex flex-wrap items-center gap-4 mb-6">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Mitglieder durchsuchen..."
                icon="magnifying-glass"
                class="flex-1 min-w-64"
            />
            <flux:checkbox wire:model.live="onlyCompleteUsers" label="Nur vollständige User" />
            <flux:checkbox wire:model.live="onlyRecoveryEnrolled" label="Nur mit Account Recovery" />
            <flux:checkbox wire:model.live="onlyNeedsConfirm" label="Nur unbestätigt" />
            <flux:checkbox wire:model.live="includeCollections" label="Collections anzeigen" />
            <flux:checkbox wire:model.live="includeGroups" label="Gruppen anzeigen" />
        </div>

        @if(config('app.debug') && !empty($this->members))
            <flux:callout variant="info" class="mb-4">
                <div class="text-xs">
                    <strong>Debug Info:</strong><br>
                    Members Count: {{ count($this->members) }}<br>
                    Filtered Count: {{ $this->filteredMembers->total() }}<br>
                    First Member: {{ json_encode($this->members[0] ?? null, JSON_PRETTY_PRINT) }}
                </div>
            </flux:callout>
        @endif

        @if($loading && empty($this->members))
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        @elseif($this->filteredMembers->isEmpty())
            <flux:callout variant="info" icon="information-circle">
                @if(empty($search))
                    Keine Mitglieder gefunden.
                    @if(config('app.debug'))
                        <div class="mt-2 text-xs">
                            Debug: members count = {{ count($this->members ?? []) }},
                            filtered count = {{ $this->filteredMembers->total() }}
                        </div>
                    @endif
                @else
                    Keine Mitglieder gefunden, die "{{ $search }}" enthalten.
                @endif
            </flux:callout>
        @else
            <flux:table :paginate="$this->filteredMembers">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>E-Mail</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Typ</flux:table.column>
                    <flux:table.column>Zugriff auf alle</flux:table.column>
                    <flux:table.column>Account Recovery</flux:table.column>
                    @if($includeGroups)
                        <flux:table.column>Gruppen</flux:table.column>
                    @endif
                    <flux:table.column align="end">Aktionen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->filteredMembers as $member)
                        @php
                            // Versuche verschiedene ID-Felder
                            $memberId = $member['id'] ?? $member['userId'] ?? $member['memberId'] ?? null;
                            $needsConfirm = $this->memberNeedsConfirm($member);
                            $status = $this->memberStatus($member);
                        @endphp
                        @if(!empty($member) && !empty($memberId))
                        <flux:table.row wire:key="member-{{ $memberId }}">
                            <flux:table.cell>
                                <flux:heading size="sm">{{ $this->memberDisplayName($member) }}</flux:heading>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $member['email'] ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($status === 2)
                                    <flux:badge variant="success" icon="check">{{ $this->memberStatusLabel($member) }}</flux:badge>
                                @elseif($needsConfirm)
                                    <flux:badge variant="warning" icon="exclamation-triangle">{{ $this->memberStatusLabel($member) }}</flux:badge>
                                @elseif($status === 0)
                                    <flux:badge variant="neutral">{{ $this->memberStatusLabel($member) }}</flux:badge>
                                @else
                                    <flux:badge variant="neutral">{{ $this->memberStatusLabel($member) }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge variant="neutral">
                                    @php
                                        $type = $member['type'] ?? 'User';
                                        echo match($type) {
                                            '0', 'Owner' => 'Owner',
                                            '1', 'Admin' => 'Admin',
                                            '2', 'User' => 'User',
                                            '3', 'Manager' => 'Manager',
                                            '4', 'Custom' => 'Custom',
                                            default => $type,
                                        };
                                    @endphp
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($member['accessAll'] ?? false)
                                    <flux:badge variant="success" icon="check">Ja</flux:badge>
                                @else
                                    <flux:badge variant="neutral">Nein</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($this->isRecoveryEnrolled($member))
                                    <flux:badge variant="success" icon="key">Registriert</flux:badge>
                                @else
                                    <flux:badge variant="neutral">Nicht registriert</flux:badge>
                                @endif
                            </flux:table.cell>
                            @if($includeGroups)
                                <flux:table.cell>
                                    {{ count($member['groups'] ?? []) }} Gruppen
                                </flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-2">
                                    @if($needsConfirm)
                                        <flux:button
                                            wire:click="confirmMember('{{ $memberId }}')"
                                            wire:confirm="Mitglied wirklich bestätigen? Danach erhält der User Zugriff auf die Organisation."
                                            variant="primary"
                                            icon="check"
                                            size="sm"
                                        >
                                            Bestätigen
                                        </flux:button>
                                    @endif
                                    <flux:button
                                        href="{{ route('apps.bitwarden.admin.members.show', ['memberId' => $memberId]) }}"
                                        variant="ghost"
                                        icon="eye"
                                        size="sm"
                                        wire:navigate
                                    >
                                        Anzeigen
                                    </flux:button>
                                    <flux:button
                                        href="{{ route('apps.bitwarden.admin.members.edit', ['memberId' => $memberId]) }}"
                                        variant="ghost"
                                        icon="pencil"
                                        size="sm"
                                        wire:navigate
                                    >
                                        Bearbeiten
                                    </flux:button>
                                    <flux:button
                                        wire:click="deleteMember('{{ $memberId }}')"
                                        wire:confirm="Möchten Sie dieses Mitglied wirklich löschen?"
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
</div>
