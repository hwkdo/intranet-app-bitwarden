<?php

use function Livewire\Volt\{title};

title('Bitwarden - Übersicht');

?>
<div>
    <x-intranet-app-bitwarden::bitwarden-layout heading="Übersicht" subheading="Sammlungen, Gruppen und Mitglieder an einem Ort." icon="key">
        <x-intranet-app-bitwarden::section icon="squares-2x2" title="Bereiche" description="Die Navigation führt Sie zu Ihren Sammlungen und zur Verwaltung.">
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                <a href="{{ route('apps.bitwarden.meine-sammlungen') }}" wire:navigate class="flex items-center gap-3 rounded-xl bg-white/70 px-4 py-3 ring-1 ring-[#d0e3f9] transition hover:bg-[#d0e3f9]/50 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                        <flux:icon icon="folder" variant="micro" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-[#073070] dark:text-white">Meine Sammlungen</span>
                        <span class="block text-xs text-slate-600 dark:text-white/70">Ihre Gruppen und Collections</span>
                    </span>
                </a>
                <a href="{{ route('apps.bitwarden.info') }}" wire:navigate class="flex items-center gap-3 rounded-xl bg-white/70 px-4 py-3 ring-1 ring-[#d0e3f9] transition hover:bg-[#d0e3f9]/50 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                        <flux:icon icon="information-circle" variant="micro" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-[#073070] dark:text-white">App-Info</span>
                        <span class="block text-xs text-slate-600 dark:text-white/70">Version und Release-Historie</span>
                    </span>
                </a>
            </div>
        </x-intranet-app-bitwarden::section>

        @can('manage-app-bitwarden')
            <x-intranet-app-bitwarden::section icon="shield-check" title="Verwaltung" description="Gruppen, Mitglieder, Collections und GVP.">
                <div class="grid gap-3 p-5 sm:grid-cols-2">
                    @foreach ([
                        ['route' => 'apps.bitwarden.admin.index', 'icon' => 'shield-check', 'label' => 'Admin', 'text' => 'Einstellungen und Wartung'],
                        ['route' => 'apps.bitwarden.admin.groups.index', 'icon' => 'user-group', 'label' => 'Gruppen', 'text' => 'Gruppen verwalten'],
                        ['route' => 'apps.bitwarden.admin.members.index', 'icon' => 'users', 'label' => 'Mitglieder', 'text' => 'Mitglieder verwalten'],
                        ['route' => 'apps.bitwarden.admin.collections.index', 'icon' => 'folder', 'label' => 'Collections', 'text' => 'Collections verwalten'],
                        ['route' => 'apps.bitwarden.admin.gvp.index', 'icon' => 'building-office', 'label' => 'GVP', 'text' => 'GVP verwalten'],
                    ] as $link)
                        <a href="{{ route($link['route']) }}" wire:navigate wire:key="overview-{{ $link['route'] }}" class="flex items-center gap-3 rounded-xl bg-white/70 px-4 py-3 ring-1 ring-[#d0e3f9] transition hover:bg-[#d0e3f9]/50 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10">
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
            </x-intranet-app-bitwarden::section>
        @endcan
    </x-intranet-app-bitwarden::bitwarden-layout>
</div>
