@props([
    'heading' => '',
    'subheading' => '',
    'icon' => 'key',
    'navItems' => [],
])

@php
    $pendingNavBadge = null;

    if (auth()->user()?->can('manage-app-bitwarden')) {
        try {
            $pendingCount = app(\Hwkdo\IntranetAppBitwarden\Services\ConfirmPendingMembersService::class)->pendingCount();
            if ($pendingCount > 0) {
                $pendingNavBadge = $pendingCount;
            }
        } catch (\Throwable) {
            $pendingNavBadge = null;
        }
    }

    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.bitwarden.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen'],
        ['label' => 'Meine Sammlungen', 'href' => route('apps.bitwarden.meine-sammlungen'), 'icon' => 'folder', 'description' => 'Ihre Gruppen und Collections', 'buttonText' => 'Sammlungen öffnen'],
        ['label' => 'Beispielseite', 'href' => route('apps.bitwarden.example'), 'icon' => 'document-text', 'description' => 'Beispielseite anzeigen', 'buttonText' => 'Beispielseite öffnen'],
        ['label' => 'App-Info', 'href' => route('apps.bitwarden.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
        ['label' => 'Admin', 'href' => route('apps.bitwarden.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-bitwarden'],
        ['label' => 'Gruppen', 'href' => route('apps.bitwarden.admin.groups.index'), 'icon' => 'user-group', 'description' => 'Gruppen verwalten', 'buttonText' => 'Gruppen öffnen', 'permission' => 'manage-app-bitwarden'],
        ['label' => 'Mitglieder', 'href' => route('apps.bitwarden.admin.members.index'), 'icon' => 'users', 'description' => 'Mitglieder verwalten', 'buttonText' => 'Mitglieder öffnen', 'permission' => 'manage-app-bitwarden'],
        ['label' => 'Unbestätigt', 'href' => route('apps.bitwarden.admin.members.pending'), 'icon' => 'clock', 'description' => 'Mitglieder mit ausstehendem Confirm', 'buttonText' => 'Unbestätigte öffnen', 'permission' => 'manage-app-bitwarden', 'badge' => $pendingNavBadge, 'badgeColor' => 'red'],
        ['label' => 'Collections', 'href' => route('apps.bitwarden.admin.collections.index'), 'icon' => 'folder', 'description' => 'Collections verwalten', 'buttonText' => 'Collections öffnen', 'permission' => 'manage-app-bitwarden'],
        ['label' => 'GVP', 'href' => route('apps.bitwarden.admin.gvp.index'), 'icon' => 'building-office', 'description' => 'GVP verwalten', 'buttonText' => 'GVP öffnen', 'permission' => 'manage-app-bitwarden'],
    ];

    $navItems = ! empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('bitwarden');
    $currentPath = '/'.ltrim(request()->path(), '/');

    $activeHref = null;
    $bestLength = -1;

    foreach ($navItems as $navItem) {
        if (($navItem['type'] ?? '') === 'separator' || empty($navItem['href'])) {
            continue;
        }

        if (isset($navItem['permission']) && ! auth()->user()?->can($navItem['permission'])) {
            continue;
        }

        $path = '/'.ltrim((string) parse_url((string) $navItem['href'], PHP_URL_PATH), '/');
        $matches = $path === '/apps/bitwarden'
            ? $currentPath === $path
            : ($currentPath === $path || str_starts_with($currentPath, $path.'/'));

        if ($matches && strlen($path) > $bestLength) {
            $bestLength = strlen($path);
            $activeHref = $navItem['href'];
        }
    }
@endphp

@if ($customBgUrl)
    @push('app-styles')
        <style data-app-bg data-ts="{{ uniqid() }}">
            :root { --app-bg-image: url('{{ $customBgUrl }}'); }
        </style>
    @endpush
@endif

<div class="hwk-bitwarden space-y-4">
    <style>
        .hwk-bitwarden {
            --color-accent: #073070;
            --color-accent-content: #073070;
            --color-accent-foreground: #ffffff;
        }

        .dark .hwk-bitwarden {
            --color-accent: #d0e3f9;
            --color-accent-content: #d0e3f9;
            --color-accent-foreground: #04214e;
        }
    </style>

    <section class="hwk-hero relative overflow-hidden rounded-2xl text-white shadow-xl shadow-[#073070]/25 ring-1 ring-white/10 motion-safe:animate-rise-in">
        <div aria-hidden="true" class="pointer-events-none absolute -top-28 -right-20 size-96 rounded-full bg-[#a0c7f2]/40 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-36 -left-20 size-80 rounded-full bg-[#d0e3f9]/30 blur-3xl"></div>
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 opacity-10 [background-image:linear-gradient(to_right,white_1px,transparent_1px),linear-gradient(to_bottom,white_1px,transparent_1px)] [background-size:2rem_2rem] [mask-image:radial-gradient(ellipse_at_top_right,black,transparent_70%)]"
        ></div>

        <div class="relative flex flex-wrap items-center gap-x-8 gap-y-5 p-5 sm:px-7 sm:py-6">
            <div class="flex min-w-0 flex-1 basis-80 items-center gap-4">
                <span class="hidden size-14 shrink-0 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/20 backdrop-blur-sm sm:grid">
                    <flux:icon :icon="$icon" class="size-7" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-[0.2em] text-[#d0e3f9] uppercase">Bitwarden</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $heading }}</h1>
                    @if (filled($subheading))
                        <p class="mt-1 max-w-2xl text-sm text-[#d0e3f9]/90">{{ $subheading }}</p>
                    @endif
                </div>
            </div>

            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </section>

    <div class="flex items-start gap-6 max-lg:flex-col">
        <nav
            aria-label="Bitwarden"
            class="glass-card w-full shrink-0 p-2 lg:sticky lg:top-4 lg:w-64"
            style="--glass-opacity: 0.92"
        >
            <ul class="space-y-0.5">
                @foreach ($navItems as $navItem)
                    @if (($navItem['type'] ?? '') === 'separator')
                        @continue
                    @endif
                    @if (isset($navItem['permission']) && ! auth()->user()?->can($navItem['permission']))
                        @continue
                    @endif
                    @php
                        $current = isset($navItem['href']) && $navItem['href'] === $activeHref;
                    @endphp
                    <li>
                        <a
                            href="{{ $navItem['href'] }}"
                            wire:navigate
                            @class([
                                'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]',
                                'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $current,
                                'text-[#073070] hover:bg-[#d0e3f9]/80 dark:text-white dark:hover:bg-white/10' => ! $current,
                            ])
                            @if ($current) aria-current="page" @endif
                        >
                            <flux:icon :icon="$navItem['icon'] ?? 'document-text'" variant="micro" class="size-4 shrink-0" />
                            <span class="truncate">{{ $navItem['label'] }}</span>
                            @if (isset($navItem['badge']) && $navItem['badge'] !== null && $navItem['badge'] !== '')
                                <span class="ml-auto inline-flex rounded-full bg-red-600 px-1.5 text-xs font-semibold text-white">{{ $navItem['badge'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 flex-1 space-y-4">
            {{ $slot }}
        </div>
    </div>
</div>

@push('nav-items')
    @json($navItems)
@endpush
