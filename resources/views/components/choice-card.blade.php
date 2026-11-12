@props([
    'type' => 'radio',          // radio | checkbox
    'icon' => null,
    'title',
    'description' => null,
    'badge' => null,
    'badgeTone' => 'zinc',      // zinc | emerald | amber | brand
    'size' => 'md',             // md | sm
])

@php
    $badgeClasses = match ($badgeTone) {
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300',
        'brand' => 'bg-brand-50 text-brand-800 ring-brand-700/20 dark:bg-brand-400/10 dark:text-brand-200',
        default => 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-white/5 dark:text-zinc-300',
    };
@endphp

{{-- Input natif (wire:model fonctionne tel quel), visuel piloté par :has(:checked). --}}
<label {{ $attributes->only('class')->class([
    'group relative flex cursor-pointer items-start gap-3 rounded-xl border bg-white text-start transition',
    'border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50/60 dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-white/20',
    'has-checked:border-brand-700 has-checked:bg-brand-50/60 has-checked:ring-1 has-checked:ring-brand-700',
    'dark:has-checked:border-brand-300 dark:has-checked:bg-brand-400/10 dark:has-checked:ring-brand-300',
    'has-focus-visible:ring-2 has-focus-visible:ring-brand-500/40',
    'has-disabled:cursor-not-allowed has-disabled:opacity-50',
    'p-4' => $size === 'md',
    'px-3.5 py-3' => $size === 'sm',
]) }}>
    <input type="{{ $type }}" {{ $attributes->except('class') }} class="peer sr-only">

    @if ($icon)
        <span @class([
            'flex shrink-0 items-center justify-center rounded-lg transition',
            'bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400',
            'group-has-checked:bg-brand-800 group-has-checked:text-white dark:group-has-checked:bg-brand-300 dark:group-has-checked:text-brand-950',
            'size-9' => $size === 'md',
            'size-8' => $size === 'sm',
        ])>
            <flux:icon :icon="$icon" variant="outline" @class(['size-5' => $size === 'md', 'size-4' => $size === 'sm']) />
        </span>
    @endif

    <span class="min-w-0 flex-1 {{ $icon ? 'pt-0.5' : '' }}">
        <span class="flex flex-wrap items-center gap-x-2 gap-y-1 pe-6">
            <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</span>
            @if ($badge)
                <span class="rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $badgeClasses }}">{{ $badge }}</span>
            @endif
        </span>
        @if ($description)
            <span class="mt-0.5 block text-[13px] leading-snug text-zinc-500 dark:text-zinc-400">{{ $description }}</span>
        @endif
        {{ $slot }}
    </span>

    {{-- Indicateur --}}
    <span @class([
        'absolute top-4 right-4 flex size-[18px] items-center justify-center border transition',
        'rounded-full' => $type === 'radio',
        'rounded-[5px]' => $type === 'checkbox',
        'border-zinc-300 bg-white dark:border-white/20 dark:bg-transparent',
        'group-has-checked:border-brand-800 group-has-checked:bg-brand-800 dark:group-has-checked:border-brand-300 dark:group-has-checked:bg-brand-300',
        '!top-3.5' => $size === 'sm',
    ]) aria-hidden="true">
        @if ($type === 'radio')
            <span class="size-1.5 rounded-full bg-white opacity-0 transition group-has-checked:opacity-100 dark:bg-brand-950"></span>
        @else
            <flux:icon.check variant="micro" class="size-3 text-white opacity-0 transition group-has-checked:opacity-100 dark:text-brand-950" />
        @endif
    </span>
</label>
