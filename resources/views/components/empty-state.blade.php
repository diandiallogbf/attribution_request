@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-14 text-center') }}>
    <div class="relative mb-5">
        <div class="absolute inset-0 -m-3 rounded-full bg-brand-50 dark:bg-brand-400/5"></div>
        <span class="relative flex size-12 items-center justify-center rounded-xl bg-white shadow-(--shadow-card) ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-white/10">
            <flux:icon :icon="$icon" class="size-6 text-brand-700 dark:text-brand-300" />
        </span>
    </div>
    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
