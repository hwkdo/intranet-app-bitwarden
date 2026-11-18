<?php

use Flux\Flux;
use Hwkdo\IntranetAppBitwarden\Jobs\RunBitwardenFullResetJob;
use Hwkdo\IntranetAppBitwarden\Services\BitwardenFullResetService;
use Hwkdo\IntranetAppBitwarden\Support\BitwardenSyncGuard;

use function Livewire\Volt\{computed, mount, state, title};

title('Bitwarden - Admin');

mount(function (): void {
    $tab = request()->query('tab');

    if ($tab === 'hintergrund') {
        $this->activeTab = 'hintergrund';
    }
});

state([
    'activeTab' => 'einstellungen',
    'resetKeepEmail' => BitwardenFullResetService::DEFAULT_KEEP_EMAIL,
    'resetDeleteAccounts' => true,
    'resetConfirmPhrase' => '',
    'resetLoading' => false,
]);

$lastResetResult = computed(function () {
    return app(BitwardenFullResetService::class)->lastResult();
});

$resetInProgress = computed(function () {
    return BitwardenSyncGuard::isPaused();
});

$canSubmitReset = computed(function () {
    return trim($this->resetConfirmPhrase) === BitwardenFullResetService::CONFIRM_PHRASE
        && filter_var(trim($this->resetKeepEmail), FILTER_VALIDATE_EMAIL)
        && ! $this->resetLoading;
});

$queueFullReset = function () {
    if (trim($this->resetConfirmPhrase) !== BitwardenFullResetService::CONFIRM_PHRASE) {
        Flux::toast('Bitte die Bestätigungsphrase exakt eingeben: '.BitwardenFullResetService::CONFIRM_PHRASE, variant: 'danger');

        return;
    }

    $keepEmail = strtolower(trim($this->resetKeepEmail));

    if (! filter_var($keepEmail, FILTER_VALIDATE_EMAIL)) {
        Flux::toast('Ungültige Keep-E-Mail.', variant: 'danger');

        return;
    }

    $this->resetLoading = true;

    try {
        RunBitwardenFullResetJob::dispatch(
            keepEmail: $keepEmail,
            deleteAccounts: (bool) $this->resetDeleteAccounts,
            dryRun: false,
            delayMs: 400,
            initiatedByUserId: auth()->id(),
        );

        $this->resetConfirmPhrase = '';
        unset($this->lastResetResult, $this->resetInProgress);

        Flux::toast(
            'Full Reset wurde in die Queue gestellt. Fortschritt siehe Laravel-Log / Horizon.',
            variant: 'warning',
        );
    } catch (\Exception $e) {
        Flux::toast('Full Reset konnte nicht gestartet werden: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->resetLoading = false;
    }
};

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Administration" subheading="Einstellungen, Wartung und Hintergrund der Bitwarden-App." icon="shield-check">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['route' => 'apps.bitwarden.admin.groups.index', 'icon' => 'user-group', 'label' => 'Gruppen', 'text' => 'Gruppen verwalten'],
            ['route' => 'apps.bitwarden.admin.members.index', 'icon' => 'users', 'label' => 'Mitglieder', 'text' => 'Mitglieder verwalten'],
            ['route' => 'apps.bitwarden.admin.members.pending', 'icon' => 'clock', 'label' => 'Unbestätigt', 'text' => 'Confirm ausstehend'],
        ] as $link)
            <a href="{{ route($link['route']) }}" wire:navigate wire:key="admin-link-{{ $link['route'] }}" class="glass-card flex items-center gap-3 p-4 transition hover:ring-1 hover:ring-[#073070]/30">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                    <flux:icon :icon="$link['icon']" variant="micro" class="size-5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-[#073070] dark:text-white">{{ $link['label'] }}</span>
                    <span class="block text-xs text-slate-600 dark:text-white/70">{{ $link['text'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    @php
        $adminTabs = [
            ['name' => 'hintergrundbild', 'icon' => 'photo', 'label' => 'Hintergrundbild'],
            ['name' => 'einstellungen', 'icon' => 'cog-6-tooth', 'label' => 'Einstellungen'],
            ['name' => 'wartung', 'icon' => 'exclamation-triangle', 'label' => 'Wartung'],
            ['name' => 'statistiken', 'icon' => 'chart-bar', 'label' => 'Statistiken'],
            ['name' => 'hintergrund', 'icon' => 'clock', 'label' => 'Hintergrund'],
        ];
    @endphp

    <div class="glass-card sticky top-3 z-20 p-3" style="--glass-opacity: 0.92">
        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Administrationsbereiche">
            @foreach ($adminTabs as $tab)
                <x-intranet-app-bitwarden::pill
                    wire:key="admin-tab-{{ $tab['name'] }}"
                    :icon="$tab['icon']"
                    :active="$activeTab === $tab['name']"
                    wire:click="$set('activeTab', '{{ $tab['name'] }}')"
                    role="tab"
                    :aria-selected="$activeTab === $tab['name'] ? 'true' : 'false'"
                >{{ $tab['label'] }}</x-intranet-app-bitwarden::pill>
            @endforeach
        </div>
    </div>

    @if ($activeTab === 'hintergrundbild')
        <div class="glass-card min-h-[400px] overflow-hidden p-5">
            @livewire('intranet-app-base::app-background-image', ['appIdentifier' => 'bitwarden'], key('bitwarden-admin-background'))
        </div>
    @elseif ($activeTab === 'einstellungen')
        <div class="min-h-[400px]">
            @livewire('intranet-app-base::admin-settings', [
                'appIdentifier' => 'bitwarden',
                'settingsModelClass' => '\Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings',
                'appSettingsClass' => '\Hwkdo\IntranetAppBitwarden\Data\AppSettings',
            ], key('bitwarden-admin-settings'))
        </div>
    @elseif ($activeTab === 'wartung')
        <div class="space-y-4">
            <x-intranet-app-bitwarden::note icon="exclamation-triangle" title="Full Reset">
                Löscht alle Collections und Gruppen in Bitwarden, entfernt alle Org-Mitglieder
                außer dem Keep-Konto und leert die Bitwarden-IDs auf allen GVPs.
                Der Lauf erfolgt asynchron in der Queue (Rate-Limit ~400&nbsp;ms zwischen API-Calls).
            </x-intranet-app-bitwarden::note>

            @if ($this->resetInProgress)
                <x-intranet-app-bitwarden::note icon="arrow-path" title="Full Reset läuft">
                    Ein Full Reset scheint aktiv oder kürzlich gestartet. Bitte prüfen Sie Logs und Horizon.
                </x-intranet-app-bitwarden::note>
            @endif

            <x-intranet-app-bitwarden::section icon="exclamation-triangle" title="Full Reset starten" description="Diese Aktion kann nicht rückgängig gemacht werden.">
                <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                    <x-intranet-app-bitwarden::row label="Keep-Konto" description="Standard: do.it@hwk-do.de. Dieses Konto bleibt erhalten." wide>
                        <flux:input wire:model="resetKeepEmail" type="email" />
                    </x-intranet-app-bitwarden::row>
                    <x-intranet-app-bitwarden::row label="Konten löschen" description="Vaultwarden-Konten ebenfalls löschen, nicht nur die Org-Mitgliedschaft.">
                        <flux:checkbox wire:model="resetDeleteAccounts" label="Konten löschen" />
                    </x-intranet-app-bitwarden::row>
                    <x-intranet-app-bitwarden::row label="Bestätigung" description="Tippen Sie exakt FULL RESET." wide>
                        <flux:input wire:model.live="resetConfirmPhrase" placeholder="FULL RESET" autocomplete="off" />
                    </x-intranet-app-bitwarden::row>
                </div>
                <div class="flex justify-end border-t border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                    <flux:button
                        variant="danger"
                        icon="trash"
                        wire:click="queueFullReset"
                        wire:confirm="Wirklich Full Reset in die Queue stellen? Das kann nicht rückgängig gemacht werden."
                        :disabled="! $this->canSubmitReset"
                        wire:loading.attr="disabled"
                    >
                        Full Reset in Queue stellen
                    </flux:button>
                </div>
            </x-intranet-app-bitwarden::section>

            @if ($this->lastResetResult)
                @php($last = $this->lastResetResult)
                <x-intranet-app-bitwarden::section icon="clipboard-document-list" title="Letztes Ergebnis" description="{{ ($last['finished_at'] ?? '—') }}{{ ! empty($last['dry_run']) ? ' · Dry-Run' : '' }} · Keep: {{ $last['keep_email'] ?? '—' }}">
                    <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                        <x-intranet-app-bitwarden::row label="GVPs geleert">{{ $last['cleared_gvps'] ?? 0 }}</x-intranet-app-bitwarden::row>
                        <x-intranet-app-bitwarden::row label="Collections gelöscht">{{ $last['deleted_collections'] ?? 0 }}</x-intranet-app-bitwarden::row>
                        <x-intranet-app-bitwarden::row label="Gruppen gelöscht">{{ $last['deleted_groups'] ?? 0 }}</x-intranet-app-bitwarden::row>
                        <x-intranet-app-bitwarden::row label="Org-Mitglieder entfernt">{{ $last['removed_org_members'] ?? 0 }}</x-intranet-app-bitwarden::row>
                        <x-intranet-app-bitwarden::row label="Konten gelöscht">{{ $last['deleted_user_accounts'] ?? 0 }}</x-intranet-app-bitwarden::row>
                        <x-intranet-app-bitwarden::row label="Fehler">{{ $last['failed'] ?? 0 }}</x-intranet-app-bitwarden::row>
                    </div>
                    @if (! empty($last['errors']))
                        <div class="space-y-1 border-t border-[#d0e3f9] px-5 py-4 text-sm text-red-700 dark:border-white/10 dark:text-red-300">
                            @foreach (array_slice($last['errors'], 0, 10) as $error)
                                <p wire:key="reset-error-{{ $loop->index }}">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                </x-intranet-app-bitwarden::section>
            @endif
        </div>
    @elseif ($activeTab === 'statistiken')
        <x-intranet-app-bitwarden::section icon="chart-bar" title="App-Statistiken" description="Übersicht über die Nutzung der Bitwarden App.">
            <div class="grid gap-3 p-5 sm:grid-cols-3">
                <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-[#d0e3f9] dark:bg-white/5 dark:ring-white/10">
                    <p class="text-xs text-slate-600 dark:text-white/70">Aktive Benutzer</p>
                    <p class="mt-1 text-2xl font-semibold text-[#073070] dark:text-white">42</p>
                </div>
                <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-[#d0e3f9] dark:bg-white/5 dark:ring-white/10">
                    <p class="text-xs text-slate-600 dark:text-white/70">Seitenaufrufe</p>
                    <p class="mt-1 text-2xl font-semibold text-[#073070] dark:text-white">1,234</p>
                </div>
                <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-[#d0e3f9] dark:bg-white/5 dark:ring-white/10">
                    <p class="text-xs text-slate-600 dark:text-white/70">Letzte Aktivität</p>
                    <p class="mt-1 text-2xl font-semibold text-[#073070] dark:text-white">2 Min</p>
                </div>
            </div>
        </x-intranet-app-bitwarden::section>
    @elseif ($activeTab === 'hintergrund')
        <div class="glass-card min-h-[400px] overflow-hidden p-5">
            @livewire('intranet-app-base::background-activities', ['appIdentifier' => 'bitwarden'], key('bitwarden-admin-hintergrund'))
        </div>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
