@props([
    'active' => false,
    'icon' => null,
])

<button
    type="button"
    {{ $attributes->class([
        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]',
        'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $active,
        'bg-white/80 text-[#073070] ring-1 ring-[#d0e3f9] hover:bg-[#d0e3f9]/80 dark:bg-white/10 dark:text-white dark:ring-white/15 dark:hover:bg-white/15' => ! $active,
    ]) }}
>
    @if (filled($icon))
        <flux:icon :icon="$icon" variant="micro" class="size-3.5" />
    @endif
    {{ $slot }}
</button>
