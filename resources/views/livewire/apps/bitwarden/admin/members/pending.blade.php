<?php

use App\Models\User;
use Flux\Flux;
use Hwkdo\BitwardenLaravel\Support\OrganizationMemberStatus;
use Hwkdo\IntranetAppBitwarden\Services\ConfirmPendingMembersService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

use function Livewire\Volt\{state, title, computed, mount, usesPagination};

title('Bitwarden - Unbestätigte Mitglieder');

usesPagination();

state([
    'loading' => false,
    'pending' => [],
    'search' => '',
]);

$confirmService = computed(fn () => app(ConfirmPendingMembersService::class));

$staleDays = computed(fn (): int => $this->confirmService()->staleDays());

$loadPending = function (): void {
    $this->loading = true;

    try {
        $this->pending = $this->confirmService()->listPendingMembers();
    } catch (\Throwable $e) {
        Flux::toast('Fehler beim Laden: '.$e->getMessage(), variant: 'danger');
        $this->pending = [];
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

$memberStatusLabel = function (array $member): string {
    return match (OrganizationMemberStatus::status($member)) {
        OrganizationMemberStatus::INVITED => 'Eingeladen',
        OrganizationMemberStatus::ACCEPTED => 'Angenommen – Confirm nötig',
        default => 'Ausstehend',
    };
};

$memberCanConfirm = function (array $member): bool {
    return OrganizationMemberStatus::needsConfirm($member);
};

$invitedAt = function (array $member): ?Carbon {
    foreach (['creationDate', 'CreationDate', 'dateCreated', 'DateCreated'] as $key) {
        $raw = $member[$key] ?? null;
        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    return null;
};

$isStale = function (array $member): bool {
    $at = $this->invitedAt($member);
    if ($at === null) {
        return false;
    }

    return $at->copy()->addDays($this->staleDays())->isPast();
};

$intranetUserEmail = function (array $member): ?string {
    $email = trim((string) ($member['email'] ?? ''));
    if ($email === '' || ! class_exists(User::class)) {
        return null;
    }

    $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

    return $user?->name ?? $user?->username;
};

$filteredPending = computed(function () {
    $members = array_values(array_filter(
        $this->pending,
        static fn ($member): bool => is_array($member) && OrganizationMemberStatus::id($member) !== '',
    ));

    if ($this->search !== '') {
        $search = strtolower($this->search);
        $members = array_values(array_filter($members, function (array $member) use ($search): bool {
            $name = strtolower((string) ($member['name'] ?? ''));
            $email = strtolower((string) ($member['email'] ?? ''));

            return str_contains($name, $search) || str_contains($email, $search);
        }));
    }

    usort($members, function (array $a, array $b): int {
        $aConfirm = OrganizationMemberStatus::needsConfirm($a) ? 0 : 1;
        $bConfirm = OrganizationMemberStatus::needsConfirm($b) ? 0 : 1;
        if ($aConfirm !== $bConfirm) {
            return $aConfirm <=> $bConfirm;
        }

        return strcasecmp((string) ($a['email'] ?? ''), (string) ($b['email'] ?? ''));
    });

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

$confirmMember = function (string $memberId): void {
    $this->loading = true;

    try {
        $result = $this->confirmService()->confirmMemberById($memberId);
        if ($result['confirmed'] > 0) {
            Flux::toast('Mitglied bestätigt', variant: 'success');
        } else {
            $message = $result['errors'][0] ?? 'Confirm fehlgeschlagen';
            Flux::toast($message, variant: 'danger');
        }
        $this->loadPending();
    } catch (\Throwable $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$confirmAllReady = function (): void {
    $this->loading = true;

    try {
        $result = $this->confirmService()->confirmAllPending(respectAutoConfirmSetting: false);
        Flux::toast(
            "{$result['confirmed']}/{$result['attempted']} Mitglieder bestätigt",
            variant: $result['errors'] === [] ? 'success' : 'warning',
        );
        $this->loadPending();
    } catch (\Throwable $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$updatedSearch = function (): void {
    $this->resetPage();
};

mount(function (): void {
    $this->loadPending();
});

?>

<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Unbestätigte Mitglieder" subheading="Bitwarden Confirm">
    <flux:card class="glass-card">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <flux:heading size="lg">Unbestätigte Mitglieder</flux:heading>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                    Automatische Bestätigung alle 15 Minuten.
                    UI-Markierung „lange unbestätigt“ nach {{ $this->staleDays }} Tagen.
                </flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="loadPending" icon="arrow-path" :disabled="$loading">
                    Aktualisieren
                </flux:button>
                <flux:button wire:click="confirmAllReady" variant="primary" icon="check" :disabled="$loading">
                    Alle bestätigbaren jetzt bestätigen
                </flux:button>
            </div>
        </div>

        <div class="mb-6">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="E-Mail oder Name…"
                icon="magnifying-glass"
                class="max-w-md"
            />
        </div>

        @if($loading && empty($pending))
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        @elseif($this->filteredPending->isEmpty())
            <flux:callout variant="success" icon="check-circle">
                Keine unbestätigten Mitglieder.
            </flux:callout>
        @else
            <flux:table :paginate="$this->filteredPending">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>E-Mail</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Intranet</flux:table.column>
                    <flux:table.column>Hinweis</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->filteredPending as $member)
                        <flux:table.row :key="$member['id'] ?? $loop->index">
                            <flux:table.cell>{{ $this->memberDisplayName($member) }}</flux:table.cell>
                            <flux:table.cell>{{ $member['email'] ?? '–' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$this->memberCanConfirm($member) ? 'amber' : 'zinc'">
                                    {{ $this->memberStatusLabel($member) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->intranetUserEmail($member) ?? '–' }}</flux:table.cell>
                            <flux:table.cell>
                                @if($this->isStale($member))
                                    <flux:badge size="sm" color="red">Lange unbestätigt</flux:badge>
                                @elseif(! $this->memberCanConfirm($member))
                                    <flux:text class="text-sm text-gray-500">Wartet auf Registrierung</flux:text>
                                @else
                                    <flux:text class="text-sm text-gray-500">Bereit für Confirm</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($this->memberCanConfirm($member))
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        wire:click="confirmMember('{{ $member['id'] }}')"
                                        :disabled="$loading"
                                    >
                                        Bestätigen
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
