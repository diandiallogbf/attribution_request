@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'back' => null,
])

<header {{ $attributes->class('mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                <flux:icon.arrow-left variant="micro" class="size-4" />
                {{ __('Retour') }}
            </a>
        @endif
        @if ($eyebrow)
            <p class="eyebrow mb-2">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-[28px] dark:text-white">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1.5 max-w-2xl text-[15px] text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
