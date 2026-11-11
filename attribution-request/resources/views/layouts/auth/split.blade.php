<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-950">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            {{-- Panneau de marque --}}
            <aside class="relative hidden overflow-hidden bg-brand-900 text-white lg:flex lg:flex-col">
                <div class="absolute inset-0 bg-linear-to-br from-brand-700 via-brand-800 to-brand-950"></div>
                {{-- Globe en filigrane --}}
                <img src="{{ asset('images/brand/agl-globe-white.png') }}" alt="" aria-hidden="true"
                     class="pointer-events-none absolute -right-40 -bottom-32 w-[640px] max-w-none opacity-[0.06] select-none">
                <div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-white/30 to-transparent"></div>

                <div class="relative z-10 flex flex-1 flex-col p-12 xl:p-16">
                    <img src="{{ asset('images/brand/agl-logo-white.png') }}" alt="AGL — Africa Global Logistics" class="h-16 w-auto self-start">

                    <div class="mt-auto max-w-md">
                        <p class="eyebrow !text-brand-200">{{ __('Direction des Systèmes d\'Information') }}</p>
                        <h1 class="mt-4 text-3xl leading-tight font-semibold tracking-tight xl:text-4xl">
                            {{ __('Vos demandes de téléphonie, sans papier.') }}
                        </h1>
                        <p class="mt-4 text-base leading-relaxed text-brand-100/80">
                            {{ __('Demandez, validez et suivez l\'attribution de téléphones et de lignes SIM en quelques étapes.') }}
                        </p>

                        <ul class="mt-10 space-y-4 text-sm">
                            @foreach ([
                                ['bolt', __('Une demande en moins de 3 minutes'), __('Formulaire guidé, informations pré-remplies.')],
                                ['check-badge', __('Validation en un clic'), __('Le manager et la DSI sont notifiés automatiquement.')],
                                ['map', __('Suivi en temps réel'), __('Chaque étape est tracée, de la commande à la remise.')],
                            ] as [$icon, $title, $text])
                                <li class="flex gap-4">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                                        <flux:icon :icon="$icon" variant="mini" class="size-4 text-white" />
                                    </span>
                                    <span>
                                        <span class="block font-medium text-white">{{ $title }}</span>
                                        <span class="text-brand-100/70">{{ $text }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <p class="mt-16 text-xs text-brand-200/60">© {{ now()->year }} Africa Global Logistics — Guinée · {{ __('Usage interne') }}</p>
                </div>
            </aside>

            {{-- Formulaire --}}
            <main class="flex flex-col items-center justify-center px-6 py-12 sm:px-12">
                <div class="w-full max-w-sm">
                    <img src="{{ asset('images/brand/agl-logo.png') }}" alt="AGL" class="mx-auto mb-10 h-14 w-auto lg:hidden dark:hidden">
                    <img src="{{ asset('images/brand/agl-logo-white.png') }}" alt="" aria-hidden="true" class="mx-auto mb-10 hidden h-14 w-auto dark:block lg:dark:hidden">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
