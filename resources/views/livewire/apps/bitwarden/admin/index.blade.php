<?php

use function Livewire\Volt\{state, title};

title('Bitwarden - Admin');

state(['activeTab' => 'einstellungen']);

?>

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
    </div>

    <flux:tab.group class="mt-6">
        <flux:tabs wire:model="activeTab">
            <flux:tab name="einstellungen" icon="cog-6-tooth">Einstellungen</flux:tab>
            <flux:tab name="statistiken" icon="chart-bar">Statistiken</flux:tab>
        </flux:tabs>
        
        <flux:tab.panel name="einstellungen">
            <div style="min-height: 400px;">
                @livewire('intranet-app-base::admin-settings', [
                    'appIdentifier' => 'bitwarden',
                    'settingsModelClass' => '\Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings',
                    'appSettingsClass' => '\Hwkdo\IntranetAppBitwarden\Data\AppSettings'
                ])
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
