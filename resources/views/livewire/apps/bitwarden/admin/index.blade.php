<?php

use Flux\Flux;
use Hwkdo\IntranetAppBitwarden\Jobs\RunBitwardenFullResetJob;
use Hwkdo\IntranetAppBitwarden\Services\BitwardenFullResetService;
use Hwkdo\IntranetAppBitwarden\Support\BitwardenSyncGuard;

use function Livewire\Volt\{state, title, computed};

title('Bitwarden - Admin');

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
<x-intranet-app-bitwarden::bitwarden-layout heading="Bitwarden App" subheading="Admin">
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <flux:card href="{{ route('apps.bitwarden.admin.groups.index') }}" class="glass-card cursor-pointer hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                    <flux:icon icon="user-group" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:heading size="md">Gruppen</flux:heading>
                    <flux:text class="text-gray-600 dark:text-gray-400">Gruppen verwalten</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card href="{{ route('apps.bitwarden.admin.members.index') }}" class="glass-card cursor-pointer hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900">
                    <flux:icon icon="users" class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <flux:heading size="md">Mitglieder</flux:heading>
                    <flux:text class="text-gray-600 dark:text-gray-400">Mitglieder verwalten</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card href="{{ route('apps.bitwarden.admin.members.pending') }}" class="glass-card cursor-pointer hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900">
                    <flux:icon icon="clock" class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:heading size="md">Unbestätigt</flux:heading>
                    <flux:text class="text-gray-600 dark:text-gray-400">Confirm ausstehend</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:tab.group class="mt-6">
        <flux:tabs wire:model="activeTab">
            <flux:tab name="hintergrundbild" icon="photo">Hintergrundbild</flux:tab>
            <flux:tab name="einstellungen" icon="cog-6-tooth">Einstellungen</flux:tab>
            <flux:tab name="wartung" icon="exclamation-triangle">Wartung</flux:tab>
            <flux:tab name="statistiken" icon="chart-bar">Statistiken</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="hintergrundbild">
            <div style="min-height: 400px;">
                @livewire('intranet-app-base::app-background-image', [
                    'appIdentifier' => 'bitwarden',
                ])
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="einstellungen">
            <div style="min-height: 400px;">
                @livewire('intranet-app-base::admin-settings', [
                    'appIdentifier' => 'bitwarden',
                    'settingsModelClass' => '\Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings',
                    'appSettingsClass' => '\Hwkdo\IntranetAppBitwarden\Data\AppSettings'
                ])
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="wartung">
            <div class="space-y-6" style="min-height: 400px;">
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.heading>Full Reset</flux:callout.heading>
                    <flux:callout.text>
                        Löscht alle Collections und Gruppen in Bitwarden, entfernt alle Org-Mitglieder
                        außer dem Keep-Konto und leert die Bitwarden-IDs auf allen GVPs.
                        Der Lauf erfolgt asynchron in der Queue (Rate-Limit ~400&nbsp;ms zwischen API-Calls).
                    </flux:callout.text>
                </flux:callout>

                @if($this->resetInProgress)
                    <flux:callout variant="warning" icon="arrow-path">
                        Ein Full Reset scheint aktiv oder kürzlich gestartet — bitte Logs/Horizon prüfen.
                    </flux:callout>
                @endif

                <flux:card class="glass-card border border-red-300 dark:border-red-800">
                    <flux:heading size="lg" class="mb-4">Full Reset starten</flux:heading>

                    <div class="space-y-4 max-w-xl">
                        <flux:field>
                            <flux:label>Keep-Konto (bleibt erhalten)</flux:label>
                            <flux:input wire:model="resetKeepEmail" type="email" />
                            <flux:description>Standard: do.it@hwk-do.de</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:checkbox wire:model="resetDeleteAccounts" label="Vaultwarden-Konten ebenfalls löschen (nicht nur Org-Mitgliedschaft)" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Bestätigung</flux:label>
                            <flux:input
                                wire:model.live="resetConfirmPhrase"
                                placeholder="FULL RESET"
                                autocomplete="off"
                            />
                            <flux:description>
                                Tippen Sie exakt <code>FULL RESET</code>
                            </flux:description>
                        </flux:field>

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
                </flux:card>

                @if($this->lastResetResult)
                    @php($last = $this->lastResetResult)
                    <flux:card class="glass-card">
                        <flux:heading size="md" class="mb-3">Letztes Ergebnis</flux:heading>
                        <flux:text variant="muted" size="sm" class="mb-3">
                            {{ $last['finished_at'] ?? '—' }}
                            @if(! empty($last['dry_run']))
                                · Dry-Run
                            @endif
                            · Keep: {{ $last['keep_email'] ?? '—' }}
                        </flux:text>
                        <ul class="space-y-1 text-sm">
                            <li>GVPs geleert: {{ $last['cleared_gvps'] ?? 0 }}</li>
                            <li>Collections gelöscht: {{ $last['deleted_collections'] ?? 0 }}</li>
                            <li>Gruppen gelöscht: {{ $last['deleted_groups'] ?? 0 }}</li>
                            <li>Org-Mitglieder entfernt: {{ $last['removed_org_members'] ?? 0 }}</li>
                            <li>Konten gelöscht: {{ $last['deleted_user_accounts'] ?? 0 }}</li>
                            <li>Fehler: {{ $last['failed'] ?? 0 }}</li>
                        </ul>
                        @if(! empty($last['errors']))
                            <div class="mt-4 space-y-1">
                                @foreach(array_slice($last['errors'], 0, 10) as $error)
                                    <flux:text class="text-red-600 text-sm">{{ $error }}</flux:text>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                @endif
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="statistiken">
            <div style="min-height: 400px;">
                <flux:card class="glass-card">
                    <flux:heading size="lg" class="mb-4">App-Statistiken</flux:heading>
                    <flux:text class="mb-6">
                        Übersicht über die Nutzung der Bitwarden App.
                    </flux:text>
                    
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Aktive Benutzer</flux:heading>
                            <flux:text size="xl" class="mt-2">42</flux:text>
                        </div>
                        
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Seitenaufrufe</flux:heading>
                            <flux:text size="xl" class="mt-2">1,234</flux:text>
                        </div>
                        
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Letzte Aktivität</flux:heading>
                            <flux:text size="xl" class="mt-2">2 Min</flux:text>
                        </div>
                    </div>
                </flux:card>
            </div>
        </flux:tab.panel>
    </flux:tab.group>
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
