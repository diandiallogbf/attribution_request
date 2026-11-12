@props([
    'title',
    'description' => null,
    'optional' => false,
])

<fieldset {{ $attributes->class('min-w-0') }}>
    <legend class="mb-3 flex w-full items-baseline justify-between gap-3">
        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</span>
        @if ($optional)
            <span class="text-xs text-zinc-400">{{ __('Facultatif') }}</span>
        @endif
    </legend>
    @if ($description)
        <p class="-mt-1.5 mb-3 text-[13px] text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif
    {{ $slot }}
</fieldset>
