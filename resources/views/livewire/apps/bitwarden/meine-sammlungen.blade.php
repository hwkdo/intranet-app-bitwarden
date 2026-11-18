<?php

use App\Models\Gvp;
use App\Models\User;
use Flux\Flux;
use Hwkdo\IntranetAppBitwarden\Models\CustomCollection;
use Hwkdo\IntranetAppBitwarden\Models\GvpBitwardenPref;
use Hwkdo\IntranetAppBitwarden\Services\MemberBitwardenScope;
use Hwkdo\IntranetAppBitwarden\Services\SupervisorBitwardenScope;
use Hwkdo\IntranetAppBitwarden\Services\SupervisorCollectionAccessService;
use Hwkdo\IntranetAppBitwarden\Services\SupervisorCustomCollectionService;

use function Livewire\Volt\{state, title, computed};

title('Bitwarden - Meine Sammlungen');

state([
    'loading' => false,
    'showCreateModal' => false,
    'createName' => '',
    'createMemberIds' => [],
    'addMemberIds' => [],
]);

$memberScope = computed(fn () => app(MemberBitwardenScope::class));
$supervisorScope = computed(fn () => app(SupervisorBitwardenScope::class));
$accessService = computed(fn () => app(SupervisorCollectionAccessService::class));
$customService = computed(fn () => app(SupervisorCustomCollectionService::class));

$visibleCards = computed(function () {
    /** @var User $user */
    $user = auth()->user();

    return $this->memberScope()->visibleCards($user);
});

$canCreateCustomCollections = computed(function () {
    /** @var User $user */
    $user = auth()->user();

    return $this->customService()->canCreate($user);
});

$selectableUsers = computed(function () {
    try {
        return $this->customService()->selectableVaultwardenUsers(auth()->user());
    } catch (\Throwable) {
        return collect();
    }
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

$customMembersFor = function (CustomCollection $collection) {
    return $this->customService()->membersFor($collection);
};

$openCreateModal = function (): void {
    $this->createName = '';
    $this->createMemberIds = [];
    $this->showCreateModal = true;
};

$createCustomCollection = function (): void {
    $this->loading = true;

    try {
        $this->validate([
            'createName' => ['required', 'string', 'max:255'],
            'createMemberIds' => ['array'],
            'createMemberIds.*' => ['integer'],
        ]);

        $memberIds = array_map('intval', $this->createMemberIds);
        $this->customService()->create(auth()->user(), $this->createName, $memberIds);
        $this->showCreateModal = false;
        $this->createName = '';
        $this->createMemberIds = [];
        Flux::toast('Sammlung angelegt', variant: 'success');
        unset($this->visibleCards);
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        Flux::toast($e->getMessage(), variant: 'danger');
    } catch (\Illuminate\Validation\ValidationException $e) {
        throw $e;
    } catch (\Exception $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$addCustomMembers = function (int $collectionId): void {
    $this->loading = true;

    try {
        $ids = array_map('intval', $this->addMemberIds[$collectionId] ?? []);
        $collection = CustomCollection::query()->findOrFail($collectionId);
        $this->customService()->addMembers(auth()->user(), $collection, $ids);
        $this->addMemberIds[$collectionId] = [];
        Flux::toast('Berechtigte hinzugefügt', variant: 'success');
        unset($this->visibleCards);
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        Flux::toast($e->getMessage(), variant: 'danger');
    } catch (\Exception $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$removeCustomMember = function (int $collectionId, int $userId): void {
    $this->loading = true;

    try {
        $collection = CustomCollection::query()->findOrFail($collectionId);
        $member = User::query()->findOrFail($userId);
        $this->customService()->removeMember(auth()->user(), $collection, $member);
        Flux::toast('Berechtigten entfernt', variant: 'success');
        unset($this->visibleCards);
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        Flux::toast($e->getMessage(), variant: 'danger');
    } catch (\Exception $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$deleteCustomCollection = function (int $collectionId): void {
    $this->loading = true;

    try {
        $collection = CustomCollection::query()->findOrFail($collectionId);
        $this->customService()->delete(auth()->user(), $collection);
        Flux::toast('Sammlung gelöscht', variant: 'success');
        unset($this->visibleCards);
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        Flux::toast($e->getMessage(), variant: 'danger');
    } catch (\Exception $e) {
        Flux::toast('Fehler: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
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
<x-intranet-app-bitwarden::bitwarden-layout heading="Meine Sammlungen" subheading="Gruppen und Collections Ihrer GVPs." icon="folder">
    @if($this->canCreateCustomCollections)
        <div class="glass-card sticky top-3 z-20 flex justify-end p-3" style="--glass-opacity: 0.92">
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal" wire:loading.attr="disabled">
                Sammlung anlegen
            </flux:button>
        </div>
    @endif

    @if($this->visibleCards->isEmpty())
        <x-intranet-app-bitwarden::empty icon="folder" title="Keine Sammlungen">
            Keine Bitwarden-Gruppen oder Collections für Sie gefunden.
        </x-intranet-app-bitwarden::empty>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach($this->visibleCards as $card)
                @if($card->isCustom())
                    @php
                        $custom = $card->customCollection;
                        $isCreator = $custom && $custom->isCreatedBy(auth()->user());
                        $customMembers = $custom ? $this->customMembersFor($custom) : collect();
                        $memberUserIds = $customMembers->pluck('id')->all();
                    @endphp
                    <section class="glass-card overflow-hidden" wire:key="meine-card-{{ $card->key() }}">
                        <div class="flex flex-col gap-4 border-b border-[#d0e3f9] px-5 py-4 sm:flex-row sm:items-start sm:justify-between dark:border-white/10">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                    <flux:icon icon="folder" variant="micro" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold text-[#073070] dark:text-white">{{ $card->title() }}</h2>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="inline-flex items-center rounded-full bg-[#073070]/10 px-2.5 py-0.5 text-xs font-semibold text-[#073070] dark:bg-white/10 dark:text-white">Manuell</span>
                                        @if($isCreator)
                                            <flux:badge variant="success">Ersteller</flux:badge>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if($isCreator)
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="deleteCustomCollection({{ $custom->id }})"
                                    wire:confirm="Sammlung „{{ $custom->name }}“ wirklich löschen?"
                                    wire:loading.attr="disabled"
                                >
                                    Löschen
                                </flux:button>
                            @endif
                        </div>

                        <div class="px-5 pb-4">
                        <flux:accordion transition>
                            <flux:accordion.item>
                                <flux:accordion.heading>
                                    Berechtigte
                                    <span class="ml-1 font-normal text-slate-600 dark:text-white/70">({{ $customMembers->count() }})</span>
                                </flux:accordion.heading>
                                <flux:accordion.content>
                                    @if($customMembers->isEmpty())
                                        <p class="py-4 text-sm text-slate-600 dark:text-white/70">Keine berechtigten Mitglieder.</p>
                                    @else
                                        <ul class="divide-y divide-[#d0e3f9] dark:divide-white/10">
                                            @foreach($customMembers as $member)
                                                <li class="flex items-center justify-between gap-3 py-3" wire:key="custom-member-{{ $custom->id }}-{{ $member->id }}">
                                                    <div>
                                                        <p class="text-sm font-medium text-[#073070] dark:text-white">{{ $member->name }}</p>
                                                        <p class="text-sm text-slate-600 dark:text-white/70">{{ $member->email }}</p>
                                                    </div>
                                                    @if($isCreator && (int) $member->id !== (int) auth()->id())
                                                        <flux:button
                                                            size="sm"
                                                            variant="ghost"
                                                            class="text-red-600"
                                                            wire:click="removeCustomMember({{ $custom->id }}, {{ $member->id }})"
                                                            wire:confirm="{{ $member->name }} entfernen?"
                                                        >
                                                            Entfernen
                                                        </flux:button>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if($isCreator)
                                        <div class="mt-4 space-y-3">
                                            <flux:select
                                                wire:model="addMemberIds.{{ $custom->id }}"
                                                label="Berechtigte hinzufügen"
                                                placeholder="Benutzer wählen…"
                                                multiple
                                                variant="listbox"
                                            >
                                                @foreach($this->selectableUsers as $option)
                                                    @if(! in_array((int) $option->id, $memberUserIds, true))
                                                        <flux:select.option value="{{ $option->id }}">
                                                            {{ $option->name }} ({{ $option->email }})
                                                        </flux:select.option>
                                                    @endif
                                                @endforeach
                                            </flux:select>
                                            <flux:button
                                                size="sm"
                                                variant="primary"
                                                wire:click="addCustomMembers({{ $custom->id }})"
                                                wire:loading.attr="disabled"
                                            >
                                                Hinzufügen
                                            </flux:button>
                                        </div>
                                    @endif
                                </flux:accordion.content>
                            </flux:accordion.item>
                        </flux:accordion>
                        </div>
                    </section>
                @else
                    @php
                        $gvp = $card->gvp;
                        $isGesamt = $card->isGesamt;
                        $canManageGvp = ! $isGesamt && $this->canManage($gvp);
                        $pref = $canManageGvp ? $this->prefFor($gvp) : null;
                        $members = $this->membersFor($gvp, $isGesamt);
                        $excluded = $canManageGvp ? $this->excludedFor($gvp) : collect();
                    @endphp
                    <section class="glass-card overflow-hidden" wire:key="meine-card-{{ $card->key() }}">
                        <div class="flex flex-col gap-4 border-b border-[#d0e3f9] px-5 py-4 sm:flex-row sm:items-start sm:justify-between dark:border-white/10">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                    <flux:icon icon="folder" variant="micro" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold text-[#073070] dark:text-white">{{ $card->title() }}</h2>
                                    @if($isGesamt)
                                        <p class="mt-1 text-xs text-slate-600 dark:text-white/70">
                                            Gesamt-Collection der Abteilung (inkl. untergeordneter Gruppen)
                                        </p>
                                    @endif
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @if($isGesamt)
                                            <flux:badge variant="success">Gesamt-Collection</flux:badge>
                                        @else
                                            @if($gvp->hasBitwardenGroup())
                                                <flux:badge variant="success">Gruppe</flux:badge>
                                            @else
                                                <flux:badge variant="neutral">Keine Gruppe</flux:badge>
                                            @endif
                                            @if($gvp->hasBitwardenCollection())
                                                <flux:badge variant="success">Collection</flux:badge>
                                            @else
                                                <flux:badge variant="neutral">Keine Collection</flux:badge>
                                            @endif
                                        @endif
                                    </div>
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

                        <div class="px-5 pb-4">
                        <flux:accordion transition>
                            <flux:accordion.item>
                                <flux:accordion.heading>
                                    Berechtigte
                                    <span class="ml-1 font-normal text-slate-600 dark:text-white/70">({{ $members->count() }})</span>
                                </flux:accordion.heading>
                                <flux:accordion.content>
                                    @if($members->isEmpty())
                                        <p class="py-4 text-sm text-slate-600 dark:text-white/70">Keine berechtigten Mitglieder.</p>
                                    @else
                                        <ul class="divide-y divide-[#d0e3f9] dark:divide-white/10">
                                            @foreach($members as $member)
                                                <li class="flex items-center justify-between gap-3 py-3" wire:key="member-{{ $card->key() }}-{{ $member->id }}">
                                                    <div>
                                                        <p class="text-sm font-medium text-[#073070] dark:text-white">{{ $member->name }}</p>
                                                        <p class="text-sm text-slate-600 dark:text-white/70">{{ $member->email }}</p>
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
                                        <span class="ml-1 font-normal text-slate-600 dark:text-white/70">({{ $excluded->count() }})</span>
                                    </flux:accordion.heading>
                                    <flux:accordion.content>
                                        <ul class="divide-y divide-[#d0e3f9] dark:divide-white/10">
                                            @foreach($excluded as $member)
                                                <li class="flex items-center justify-between gap-3 py-3" wire:key="excluded-{{ $card->key() }}-{{ $member->id }}">
                                                    <div>
                                                        <p class="text-sm font-medium text-[#073070] dark:text-white">{{ $member->name }}</p>
                                                        <p class="text-sm text-slate-600 dark:text-white/70">{{ $member->email }}</p>
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
                        </div>
                    </section>
                @endif
            @endforeach
        </div>
    @endif

    <flux:modal wire:model="showCreateModal" class="md:max-w-lg space-y-6">
        <div>
            <flux:heading size="lg">Sammlung anlegen</flux:heading>
            <flux:text class="mt-1">Unabhängige Vaultwarden-Sammlung mit ausgewählten Berechtigten.</flux:text>
        </div>

        <flux:input wire:model="createName" label="Name" placeholder="z. B. Projektzugänge" />

        <flux:select
            wire:model="createMemberIds"
            label="Berechtigte"
            placeholder="Benutzer wählen…"
            multiple
            variant="listbox"
            description="Nur Vaultwarden-Mitglieder. Sie werden automatisch als Ersteller hinzugefügt."
        >
            @foreach($this->selectableUsers as $option)
                <flux:select.option value="{{ $option->id }}">
                    {{ $option->name }} ({{ $option->email }})
                </flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="createCustomCollection" wire:loading.attr="disabled">
                Anlegen
            </flux:button>
        </div>
    </flux:modal>
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
