@php($navigation = app(\App\Support\Navigation::class)->for(auth()->user()))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        {{-- Barre latérale bleu marine AGL (palette « sombre » de Flux forcée via .dark) --}}
        <flux:sidebar sticky collapsible="mobile" class="dark sidebar-brand border-e border-white/5 bg-linear-to-b from-brand-800 to-brand-950 text-white">
            <flux:sidebar.header class="pb-2">
                <x-app-logo inverse :href="route('dashboard')" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Mon espace')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Tableau de bord') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="plus-circle" :href="route('requests.create')" :current="request()->routeIs('requests.create*', 'requests.edit', 'requests.profile.edit')" wire:navigate>
                        {{ __('Nouvelle demande') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('requests.index')" :current="request()->routeIs('requests.index', 'requests.show')" :badge="$navigation['open_requests'] ?: null" wire:navigate>
                        {{ __('Mes demandes') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="device-phone-mobile" :href="route('equipment.index')" :current="request()->routeIs('equipment.index')" wire:navigate>
                        {{ __('Mes équipements') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if ($navigation['is_approver'])
                    {{-- Pas de __('Validation') : sous Windows, la clé résout lang/fr/validation.php (insensible à la casse). --}}
                    <flux:sidebar.group :heading="__('Mes validations')" class="mt-4 grid">
                        <flux:sidebar.item icon="check-badge" :href="route('approvals.index')" :current="request()->routeIs('approvals.*')" :badge="$navigation['pending_approvals'] ?: null" badge:color="amber" wire:navigate>
                            {{ __('À valider') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if ($navigation['back_office'])
                    <flux:sidebar.group :heading="__('Administration')" class="mt-4 grid">
                        <flux:sidebar.item icon="building-office-2" href="{{ url('/admin') }}">
                            {{ __('Back-office DSI') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="rounded-xl bg-white/5 p-3 text-xs leading-relaxed text-white/70 ring-1 ring-white/10 in-data-flux-sidebar-collapsed-desktop:hidden">
                <p class="font-medium text-white">{{ __('Besoin d\'aide ?') }}</p>
                <p class="mt-0.5">{{ __('Support DSI : poste 2400') }}<br>{{ __('support.dsi@agl.test') }}</p>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        {{-- En-tête mobile --}}
        <flux:header class="border-b border-zinc-200 bg-white lg:hidden dark:border-white/10 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <x-app-logo-icon class="ms-2 h-6 dark:hidden" />
            <x-app-logo-icon inverse class="ms-2 hidden h-6 dark:block" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                <x-user-menu-items />
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
