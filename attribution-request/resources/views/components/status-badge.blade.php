@props([
    'status',      // enum implémentant getLabel() / getColor()
    'size' => 'sm',
])

@php
    // Couleur sémantique de l'enum → palette Tailwind (mêmes teintes que le back-office Filament).
    $tone = match ($status->getColor()) {
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-400/10 dark:text-orange-300 dark:ring-orange-400/20',
        'danger' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/20',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-400/10 dark:text-violet-300 dark:ring-violet-400/20',
        'primary' => 'bg-brand-50 text-brand-800 ring-brand-700/20 dark:bg-brand-400/10 dark:text-brand-200 dark:ring-brand-300/20',
        default => 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-white/5 dark:text-zinc-300 dark:ring-white/10',
    };
    $dot = match ($status->getColor()) {
        'success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'orange' => 'bg-orange-500',
        'danger' => 'bg-red-500', 'info' => 'bg-sky-500', 'violet' => 'bg-violet-500',
        'primary' => 'bg-brand-600 dark:bg-brand-300', default => 'bg-zinc-400',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center gap-1.5 rounded-full font-medium whitespace-nowrap ring-1 ring-inset',
    'px-2 py-0.5 text-xs' => $size === 'sm',
    'px-2.5 py-1 text-sm' => $size === 'md',
    $tone,
]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $status->getLabel() }}
</span>
