<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;

use function Livewire\Volt\{state, title, computed, mount};

title('Bitwarden - Mitglied anzeigen');

state([
    'memberId' => null,
    'member' => null,
    'loading' => false,
]);

$apiService = computed(fn() => app(BitwardenPublicApiService::class));


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

<x-intranet-app-bitwarden::bitwarden-layout heading="Mitglied anzeigen" subheading="Bitwarden Mitglieder">
    @if($loading)
        <flux:card class="glass-card">
            <div class="flex items-center justify-center py-12">
                <flux:icon.loading class="h-8 w-8" />
            </div>
        </flux:card>
    @elseif($member)
        <flux:card class="glass-card">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $member['name'] ?? 'Unbekannt' }}</flux:heading>
                    <flux:text class="text-gray-600 mt-1">{{ $member['email'] ?? '-' }}</flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <flux:label>Typ</flux:label>
                        <div class="mt-1">
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
                        </div>
                    </div>

                    <div>
                        <flux:label>Zugriff auf alle Collections</flux:label>
                        <div class="mt-1">
                            @if($member['accessAll'] ?? false)
                                <flux:badge variant="success" icon="check">Ja</flux:badge>
                            @else
                                <flux:badge variant="neutral">Nein</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!empty($member['groups']))
                    <div>
                        <flux:heading size="md" class="mb-4">Gruppen</flux:heading>
                        <div class="space-y-2">
                            @foreach($member['groups'] as $group)
                                <div class="flex items-center justify-between p-2 border rounded">
                                    <span>{{ $group['name'] ?? 'Unbekannt' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($member['collections']))
                    <div>
                        <flux:heading size="md" class="mb-4">Collections</flux:heading>
                        <div class="space-y-2">
                            @foreach($member['collections'] as $collection)
                                <div class="flex items-center justify-between p-2 border rounded">
                                    <span>{{ $collection['name'] ?? 'Unbekannt' }}</span>
                                    <div class="flex gap-2">
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
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <flux:button href="{{ route('apps.bitwarden.admin.members.index') }}" variant="ghost" wire:navigate>
                        Zurück
                    </flux:button>
                    <flux:button href="{{ route('apps.bitwarden.admin.members.edit', $memberId) }}" variant="primary" wire:navigate>
                        Bearbeiten
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @else
        <flux:card class="glass-card">
            <flux:callout variant="danger" icon="exclamation-triangle">
                Mitglied nicht gefunden (ID: {{ $memberId ?? 'unbekannt' }})
                @if(config('app.debug'))
                    <div class="mt-2 text-xs">
                        Bitte prüfen Sie die Laravel-Logs für weitere Details.
                    </div>
                @endif
            </flux:callout>
            <div class="mt-4">
                <flux:button href="{{ route('apps.bitwarden.admin.members.index') }}" variant="ghost" wire:navigate>
                    Zurück zur Übersicht
                </flux:button>
            </div>
        </flux:card>
    @endif
</x-intranet-app-bitwarden::bitwarden-layout>

