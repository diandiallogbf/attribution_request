@props([
    'label',
    'value',
    'icon',
    'hint' => null,
    'href' => null,
    'tone' => 'brand',
])

@php
    $iconTone = match ($tone) {
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/10 dark:bg-amber-400/10 dark:text-amber-300',
        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/10 dark:bg-emerald-400/10 dark:text-emerald-300',
        default => 'bg-brand-50 text-brand-700 ring-brand-700/10 dark:bg-brand-400/10 dark:text-brand-200',
    };
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class([
        'surface group relative flex items-start gap-4 p-5',
        'transition hover:border-zinc-300 hover:shadow-(--shadow-raised) dark:hover:border-white/20' => $href,
    ]) }}>
    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ $iconTone }}">
        <flux:icon :icon="$icon" variant="outline" class="size-5" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
        <p class="numeric mt-1 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</p>
        @endif
    </div>
    @if ($href)
        <flux:icon.arrow-up-right variant="micro" class="size-4 text-zinc-300 transition group-hover:text-zinc-500 dark:text-zinc-600" />
    @endif
</{{ $tag }}>
