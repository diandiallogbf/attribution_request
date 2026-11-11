@props(['name'])

@error($name)
    <p {{ $attributes->class('mt-2 flex items-center gap-1.5 text-sm font-medium text-red-600 dark:text-red-400') }} role="alert">
        <flux:icon.exclamation-circle variant="mini" class="size-4 shrink-0" />
        {{ $message }}
    </p>
@enderror
