<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Paramètres') }}">
            <flux:navlist.item icon="user-circle" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>{{ __('Profil') }}</flux:navlist.item>
            <flux:navlist.item icon="shield-check" :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate>{{ __('Sécurité') }}</flux:navlist.item>
            <flux:navlist.item icon="swatch" :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate>{{ __('Apparence') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $heading ?? '' }}</h2>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $subheading ?? '' }}</p>

        <div class="mt-5 w-full max-w-2xl">
            {{ $slot }}
        </div>
    </div>
</div>
