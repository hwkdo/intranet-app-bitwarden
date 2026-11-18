<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;

use function Livewire\Volt\{state, title, computed, mount};

title('Bitwarden - Mitglied anzeigen');

state([
    'memberId' => null,
    'member' => null,
    'loading' => false,
]);

$apiService = computed(fn() => app(BitwardenManagementApiInterface::class));


mount(function (string $memberId) {
    $this->memberId = $memberId;
    $this->loading = true;
    try {
        $this->member = $this->apiService()->getMember(
            $memberId,
            includeCollections: true,
            includeGroups: true
        );

        if (empty($this->member)) {
            Flux::toast('Mitglied nicht gefunden (ID: '.$memberId.')', variant: 'danger');
            return;
        }
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Error loading member', [
            'memberId' => $memberId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Laden des Mitglieds: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
});

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Mitglied" subheading="Typ, Zugriff und Zugehörigkeiten." icon="users">
    @if($loading)
        <x-intranet-app-bitwarden::section icon="users" title="Mitglied" description="Das Mitglied wird geladen.">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="size-8 text-[#073070] dark:text-white" />
            </div>
        </x-intranet-app-bitwarden::section>
    @elseif($member)
        @php
            $memberTitle = trim((string) ($member['name'] ?? '')) !== '' ? $member['name'] : ($member['email'] ?? 'Unbekannt');
        @endphp
        <x-intranet-app-bitwarden::section icon="users" :title="$memberTitle" :description="$member['email'] ?? '-'">
            <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                <x-intranet-app-bitwarden::row label="Typ">
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
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Zugriff auf alle Collections">
                    @if($member['accessAll'] ?? false)
                        <flux:badge variant="success" icon="check">Ja</flux:badge>
                    @else
                        <flux:badge variant="neutral">Nein</flux:badge>
                    @endif
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Account Recovery">
                    @if($member['resetPasswordEnrolled'] ?? $member['ResetPasswordEnrolled'] ?? false)
                        <flux:badge variant="success" icon="key">Registriert</flux:badge>
                    @else
                        <flux:badge variant="neutral">Nicht registriert</flux:badge>
                    @endif
                </x-intranet-app-bitwarden::row>
            </div>
        </x-intranet-app-bitwarden::section>

        @if(!empty($member['groups']))
            <x-intranet-app-bitwarden::section icon="user-group" title="Gruppen" description="Gruppen dieses Mitglieds.">
                <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                    @foreach($member['groups'] as $group)
                        <div class="py-4 text-sm font-medium text-[#073070] dark:text-white" wire:key="member-group-{{ $group['id'] ?? $loop->index }}">
                            {{ $group['name'] ?? 'Unbekannt' }}
                        </div>
                    @endforeach
                </div>
            </x-intranet-app-bitwarden::section>
        @endif

        @if(!empty($member['collections']))
            <x-intranet-app-bitwarden::section icon="folder" title="Collections" description="Collections dieses Mitglieds.">
                <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                    @foreach($member['collections'] as $collection)
                        <div class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between" wire:key="member-collection-{{ $collection['id'] ?? $loop->index }}">
                            <p class="text-sm font-medium text-[#073070] dark:text-white">{{ $collection['name'] ?? 'Unbekannt' }}</p>
                            <div class="flex flex-wrap gap-2">
                                @if($collection['readOnly'] ?? false)
                                    <flux:badge variant="neutral" size="sm">Nur Lesen</flux:badge>
                                @endif
                                @if($collection['hidePasswords'] ?? false)
                                    <flux:badge variant="neutral" size="sm">Passwörter versteckt</flux:badge>
                                @endif
                                @if($collection['manage'] ?? false)
                                    <flux:badge variant="neutral" size="sm">Verwalten</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-intranet-app-bitwarden::section>
        @endif

        <div class="flex items-center justify-end gap-3">
            <flux:button href="{{ route('apps.bitwarden.admin.members.index') }}" variant="ghost" wire:navigate>
                Zurück
            </flux:button>
            <flux:button href="{{ route('apps.bitwarden.admin.members.edit', $memberId) }}" variant="primary" wire:navigate>
                Bearbeiten
            </flux:button>
        </div>
    @else
        <x-intranet-app-bitwarden::empty icon="users" title="Mitglied nicht gefunden">
            Mitglied nicht gefunden (ID: {{ $memberId ?? 'unbekannt' }}).
            @if(config('app.debug'))
                <span class="mt-2 block text-xs">Bitte prüfen Sie die Laravel-Logs für weitere Details.</span>
            @endif
            <span class="mt-4 block">
                <flux:button href="{{ route('apps.bitwarden.admin.members.index') }}" variant="ghost" wire:navigate>
                    Zurück zur Übersicht
                </flux:button>
            </span>
        </x-intranet-app-bitwarden::empty>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
