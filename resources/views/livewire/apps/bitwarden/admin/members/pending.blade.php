<?php

use App\Models\User;
use Flux\Flux;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
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

$reinviteMember = function (string $memberId): void {
    $this->loading = true;

    try {
        app(BitwardenManagementApiInterface::class)->reinviteMember($memberId);
        Flux::toast('Einladung erneut gesendet', variant: 'success');
        $this->loadPending();
    } catch (\Throwable $e) {
        Flux::toast('Fehler beim erneuten Senden der Einladung: '.$e->getMessage(), variant: 'danger');
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
<x-intranet-app-bitwarden::bitwarden-layout heading="Unbestätigte Mitglieder" subheading="Automatische Bestätigung alle 15 Minuten. Die Markierung „lange unbestätigt“ erscheint nach {{ $this->staleDays }} Tagen." icon="clock">
    <div class="glass-card sticky top-3 z-20 p-3" style="--glass-opacity: 0.92">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="E-Mail oder Name …"
                    icon="magnifying-glass"
                    aria-label="Unbestätigte Mitglieder durchsuchen"
                    clearable
                />
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
    </div>

    @if($loading && empty($pending))
        <x-intranet-app-bitwarden::section icon="clock" title="Unbestätigte Mitglieder" description="Die Liste wird geladen.">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="size-8 text-[#073070] dark:text-white" />
            </div>
        </x-intranet-app-bitwarden::section>
    @elseif($this->filteredPending->isEmpty())
        <x-intranet-app-bitwarden::empty icon="check-circle" title="Keine unbestätigten Mitglieder">
            Aktuell wartet niemand auf eine Bestätigung.
        </x-intranet-app-bitwarden::empty>
    @else
        <x-intranet-app-bitwarden::section icon="clock" title="Unbestätigte Mitglieder" description="{{ $this->filteredPending->total() }} {{ $this->filteredPending->total() === 1 ? 'Mitglied' : 'Mitglieder' }}">
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
                                <flux:badge size="sm" :variant="$this->memberCanConfirm($member) ? 'warning' : 'neutral'">
                                    {{ $this->memberStatusLabel($member) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->intranetUserEmail($member) ?? '–' }}</flux:table.cell>
                            <flux:table.cell>
                                @if($this->isStale($member))
                                    <flux:badge size="sm" variant="danger">Lange unbestätigt</flux:badge>
                                @elseif(! $this->memberCanConfirm($member))
                                    <span class="text-sm text-slate-600 dark:text-white/70">Wartet auf Registrierung</span>
                                @else
                                    <span class="text-sm text-slate-600 dark:text-white/70">Bereit für Confirm</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-2">
                                    @if(OrganizationMemberStatus::canReinvite($member))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="paper-airplane"
                                            wire:click="reinviteMember('{{ $member['id'] }}')"
                                            wire:confirm="Einladung wirklich erneut senden?"
                                            :disabled="$loading"
                                        >
                                            Erneut senden
                                        </flux:button>
                                    @endif
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
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-intranet-app-bitwarden::section>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
