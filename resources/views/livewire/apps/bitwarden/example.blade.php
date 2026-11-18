<?php

use function Livewire\Volt\{state, title};

title('Bitwarden - Beispiel');

state(['exampleData' => [
    'name' => 'Beispiel Item',
    'description' => 'Dies ist ein Beispiel-Item für die Bitwarden App',
    'status' => 'active',
    'created_at' => now()->format('d.m.Y H:i'),
]]);

?>
<div>
    <x-intranet-app-bitwarden::bitwarden-layout heading="Beispielseite" subheading="So sieht eine typische Seite in der Bitwarden-App aus." icon="document-text">
        <x-intranet-app-bitwarden::section icon="document-text" title="Beispiel-Content" description="Diese Seite zeigt, wie eine typische App-Seite aussehen könnte.">
            <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                <x-intranet-app-bitwarden::row :label="$exampleData['name']" :description="$exampleData['description']">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <flux:badge variant="success">{{ $exampleData['status'] }}</flux:badge>
                        <span>{{ $exampleData['created_at'] }}</span>
                    </div>
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Weitere Informationen" description="Hier könnten weitere Details oder Aktionen für das Beispiel-Item angezeigt werden." wide>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <flux:button variant="primary" size="sm">Bearbeiten</flux:button>
                        <flux:button variant="ghost" size="sm">Löschen</flux:button>
                    </div>
                </x-intranet-app-bitwarden::row>
            </div>
        </x-intranet-app-bitwarden::section>
    </x-intranet-app-bitwarden::bitwarden-layout>
</div>
