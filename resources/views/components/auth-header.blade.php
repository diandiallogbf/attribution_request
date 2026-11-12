@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1.5">
    <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
</div>
