<div>
    <x-page-header :title="__('Nouvelle demande')" :description="__('Choisissez la fiche correspondant à votre besoin. Chacune se remplit en quelques étapes courtes.')" />

    <div class="grid gap-5 md:grid-cols-2">
        @foreach ([
            [
                'href' => route('requests.create.telephony'),
                'icon' => 'device-phone-mobile',
                'tone' => 'brand',
                'title' => __('Téléphonie'),
                'code' => 'AGL-GIN-DSI-F-0015',
                'description' => __('Téléphone professionnel et/ou ligne SIM de la flotte : attribution, remplacement, réparation, restitution, changement de forfait.'),
                'points' => [__('Grille de dotation vérifiée automatiquement'), __('Validation manager puis DSI'), __('Commande, remise et accusé de réception')],
            ],
            [
                'href' => route('requests.create.profile'),
                'icon' => 'identification',
                'tone' => 'violet',
                'title' => __('Profil utilisateur'),
                'code' => 'AGL-GIN-DSI-F-0008',
                'description' => __('Ouverture, modification ou fermeture des accès d\'un collaborateur : e-mail, AS400, Internet, applications métier et matériel informatique.'),
                'points' => [__('Nouvel arrivant, changement de poste ou départ'), __('Validation des directeurs propriétaires des applications'), __('Exécution par l\'IT puis archivage de la fiche')],
            ],
        ] as $card)
            <a href="{{ $card['href'] }}" wire:navigate
               class="surface group relative flex flex-col overflow-hidden p-6 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-(--shadow-raised) dark:hover:border-white/20">
                <div @class([
                    'absolute inset-x-0 top-0 h-1',
                    'bg-linear-to-r from-brand-700 to-brand-400' => $card['tone'] === 'brand',
                    'bg-linear-to-r from-violet-600 to-violet-400' => $card['tone'] === 'violet',
                ])></div>
                <div class="flex items-start justify-between gap-4">
                    <span @class([
                        'flex size-12 items-center justify-center rounded-xl ring-1 ring-inset',
                        'bg-brand-50 text-brand-700 ring-brand-700/10 dark:bg-brand-400/10 dark:text-brand-200' => $card['tone'] === 'brand',
                        'bg-violet-50 text-violet-700 ring-violet-600/10 dark:bg-violet-400/10 dark:text-violet-300' => $card['tone'] === 'violet',
                    ])>
                        <flux:icon :icon="$card['icon']" class="size-6" />
                    </span>
                    <span class="font-mono text-[11px] text-zinc-400">{{ $card['code'] }}</span>
                </div>
                <h2 class="mt-5 text-lg font-semibold text-zinc-900 dark:text-white">{{ $card['title'] }}</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $card['description'] }}</p>
                <ul class="mt-5 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    @foreach ($card['points'] as $point)
                        <li class="flex items-center gap-2">
                            <flux:icon.check-circle variant="mini" class="size-4 shrink-0 text-emerald-500" /> {{ $point }}
                        </li>
                    @endforeach
                </ul>
                <span class="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-brand-800 dark:text-brand-200">
                    {{ __('Commencer') }} <flux:icon.arrow-right variant="micro" class="size-4 transition group-hover:translate-x-1" />
                </span>
            </a>
        @endforeach
    </div>

    @if ($this->drafts->isNotEmpty())
        <section class="surface mt-8">
            <header class="border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Reprendre un brouillon') }}</h2>
            </header>
            @foreach ($this->drafts as $draft)
                <x-request-row :request="$draft" wire:key="draft-{{ $draft->id }}" />
            @endforeach
        </section>
    @endif
</div>
