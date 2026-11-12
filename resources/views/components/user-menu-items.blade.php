@php($user = auth()->user())

<flux:menu class="min-w-64">
    <div class="flex items-center gap-3 px-2 py-2 text-start text-sm">
        <flux:avatar :name="$user->name" :initials="$user->initials()" />
        <div class="grid flex-1 leading-tight">
            <span class="truncate font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</span>
            <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->job_title ?? $user->email }}</span>
            @if ($user->matricule)
                <span class="numeric mt-0.5 text-xs text-zinc-400">{{ __('Matricule') }} {{ $user->matricule }}</span>
            @endif
        </div>
    </div>

    <flux:menu.separator />

    <flux:menu.item :href="route('profile.edit')" icon="user-circle" wire:navigate>{{ __('Mon profil') }}</flux:menu.item>
    <flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate>{{ __('Sécurité') }}</flux:menu.item>
    <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate>{{ __('Apparence') }}</flux:menu.item>

    <flux:menu.separator />

    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
            {{ __('Se déconnecter') }}
        </flux:menu.item>
    </form>
</flux:menu>
