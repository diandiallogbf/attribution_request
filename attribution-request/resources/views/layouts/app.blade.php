<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="!p-0">
        <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
