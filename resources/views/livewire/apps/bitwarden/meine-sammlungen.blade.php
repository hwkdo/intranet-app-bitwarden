<?php

use App\Models\Gvp;
use App\Models\User;
use Flux\Flux;
use Hwkdo\IntranetAppBitwarden\Models\GvpBitwardenPref;
use Hwkdo\IntranetAppBitwarden\Services\MemberBitwardenScope;
use Hwkdo\IntranetAppBitwarden\Services\SupervisorBitwardenScope;
use Hwkdo\IntranetAppBitwarden\Services\SupervisorCollectionAccessService;

use function Livewire\Volt\{state, title, computed};

title('Bitwarden - Meine Sammlungen');

state([
    'loading' => false,
]);

$memberScope = computed(fn () => app(MemberBitwardenScope::class));
$supervisorScope = computed(fn () => app(SupervisorBitwardenScope::class));
$accessService = computed(fn () => app(SupervisorCollectionAccessService::class));

$visibleCards = computed(function () {
    /** @var User $user */
    $user = auth()->user();

    return $this->memberScope()->visibleCards($user);
});

$isSupervisor = computed(function () {
    /** @var User $user */
    $user = auth()->user();

    return $this->supervisorScope()->isSupervisor($user);
});

$canManage = function (Gvp $gvp): bool {
    /** @var User $user */
    $user = auth()->user();

    return $this->supervisorScope()->canManage($user, $gvp);
};

$prefFor = function (Gvp $gvp): GvpBitwardenPref {
    return GvpBitwardenPref::forGvp($gvp);
};

$membersFor = function (Gvp $gvp, bool $isGesamt = false) {
    if ($isGesamt) {
        return $this->accessService()->gesamtEligibleMembers($gvp);
    }

    return $this->accessService()->eligibleMembers($gvp);
};

$excludedFor = function (Gvp $gvp) {
    return $this->accessService()->excludedMembers($gvp);
};

$toggleAzubis = function (int $gvpId) {
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);
        $pref = GvpBitwardenPref::forGvp($gvp);
        $this->accessService()->setIncludeAzubis(auth()->user(), $gvp, ! $pref->include_azubis);
        Flux::toast('Azubi-Einstellung gespeichert', variant: 'success');
        unset($this->visibleCards);
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        Flux::toast($e->getMessage(), variant: 'danger');
    } catch (\Exception $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$togglePraktikanten = function (int $gvpId) {
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);
        $pref = GvpBitwardenPref::forGvp($gvp);
        $this->accessService()->setIncludePraktikanten(auth()->user(), $gvp, ! $pref->include_praktikanten);
        Flux::toast('Praktikanten-Einstellung gespeichert', variant: 'success');
        unset($this->visibleCards);
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        Flux::toast($e->getMessage(), variant: 'danger');
    } catch (\Exception $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$revokeAccess = function (int $gvpId, int $userId) {
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);
        $member = User::findOrFail($userId);
        $this->accessService()->revoke(auth()->user(), $gvp, $member);
        Flux::toast('Zugriff entzogen', variant: 'success');
        unset($this->visibleCards);
    } catch (RuntimeException $e) {
        Flux::toast($e->getMessage(), variant: 'warning');
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        Flux::toast($e->getMessage(), variant: 'danger');
    } catch (\Exception $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$restoreAccess = function (int $gvpId, int $userId) {
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);
        $member = User::findOrFail($userId);
        $this->accessService()->restore(auth()->user(), $gvp, $member);
        Flux::toast('Zugriff wieder freigegeben', variant: 'success');
        unset($this->visibleCards);
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        Flux::toast($e->getMessage(), variant: 'danger');
    } catch (\Exception $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Meine Sammlungen" subheading="Gruppen und Collections Ihrer GVPs">
    @if($this->visibleCards->isEmpty())
        <flux:callout variant="info" icon="information-circle">
            Keine Bitwarden-Gruppen oder Collections für Sie gefunden.
        </flux:callout>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach($this->visibleCards as $card)
                @php
                    $gvp = $card->gvp;
                    $isGesamt = $card->isGesamt;
                    $canManageGvp = ! $isGesamt && $this->canManage($gvp);
                    $pref = $canManageGvp ? $this->prefFor($gvp) : null;
                    $members = $this->membersFor($gvp, $isGesamt);
                    $excluded = $canManageGvp ? $this->excludedFor($gvp) : collect();
                @endphp
                <flux:card class="glass-card" wire:key="meine-card-{{ $card->key() }}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <flux:heading size="lg">{{ $card->title() }}</flux:heading>
                            @if($isGesamt)
                                <flux:text variant="muted" size="sm" class="mt-1">
                                    Gesamt-Collection der Abteilung (inkl. untergeordneter Gruppen)
                                </flux:text>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if($isGesamt)
                                    <flux:badge variant="success">Gesamt-Collection</flux:badge>
                                @else
                                    @if($gvp->hasBitwardenGroup())
                                        <flux:badge variant="success">Gruppe</flux:badge>
                                    @else
                                        <flux:badge color="zinc">Keine Gruppe</flux:badge>
                                    @endif
                                    @if($gvp->hasBitwardenCollection())
                                        <flux:badge variant="success">Collection</flux:badge>
                                    @else
                                        <flux:badge color="zinc">Keine Collection</flux:badge>
                                    @endif
                                @endif
                            </div>
                        </div>

                        @if($canManageGvp && $pref)
                            <div class="flex flex-col gap-3 sm:items-end">
                                <flux:switch
                                    wire:click="toggleAzubis({{ $gvp->id }})"
                                    :checked="$pref->include_azubis"
                                    label="Azubis berücksichtigen"
                                    align="left"
                                />
                                <flux:switch
                                    wire:click="togglePraktikanten({{ $gvp->id }})"
                                    :checked="$pref->include_praktikanten"
                                    label="Praktikanten berücksichtigen"
                                    align="left"
                                />
                            </div>
                        @endif
                    </div>

                    <flux:accordion transition class="mt-6">
                        <flux:accordion.item>
                            <flux:accordion.heading>
                                Berechtigte
                                <flux:text variant="muted" class="ml-1 font-normal">({{ $members->count() }})</flux:text>
                            </flux:accordion.heading>
                            <flux:accordion.content>
                                @if($members->isEmpty())
                                    <flux:text variant="muted">Keine berechtigten Mitglieder.</flux:text>
                                @else
                                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                        @foreach($members as $member)
                                            <li class="flex items-center justify-between gap-3 px-3 py-2" wire:key="member-{{ $card->key() }}-{{ $member->id }}">
                                                <div>
                                                    <flux:text class="font-medium">{{ $member->name }}</flux:text>
                                                    <flux:text variant="muted" size="sm">{{ $member->email }}</flux:text>
                                                </div>
                                                @if($canManageGvp && (int) $member->id !== (int) auth()->id())
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        class="text-red-600"
                                                        wire:click="revokeAccess({{ $gvp->id }}, {{ $member->id }})"
                                                        wire:confirm="Zugriff für {{ $member->name }} entziehen?"
                                                    >
                                                        Entziehen
                                                    </flux:button>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </flux:accordion.content>
                        </flux:accordion.item>

                        @if($canManageGvp && $excluded->isNotEmpty())
                            <flux:accordion.item>
                                <flux:accordion.heading>
                                    Entzogene Zugriffe
                                    <flux:text variant="muted" class="ml-1 font-normal">({{ $excluded->count() }})</flux:text>
                                </flux:accordion.heading>
                                <flux:accordion.content>
                                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                        @foreach($excluded as $member)
                                            <li class="flex items-center justify-between gap-3 px-3 py-2" wire:key="excluded-{{ $card->key() }}-{{ $member->id }}">
                                                <div>
                                                    <flux:text class="font-medium">{{ $member->name }}</flux:text>
                                                    <flux:text variant="muted" size="sm">{{ $member->email }}</flux:text>
                                                </div>
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    wire:click="restoreAccess({{ $gvp->id }}, {{ $member->id }})"
                                                >
                                                    Wieder freigeben
                                                </flux:button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </flux:accordion.content>
                            </flux:accordion.item>
                        @endif
                    </flux:accordion>
                </flux:card>
            @endforeach
        </div>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
