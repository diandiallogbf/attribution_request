@php
    $keys = array_keys($this->steps);
    $currentIndex = array_search($step, $keys, true);
    $current = $this->steps[$step];
@endphp

<div>
    <x-page-header
        :eyebrow="__('Fiche d\'attribution de téléphone')"
        :title="$isResubmission ? __('Corriger la demande') : __('Nouvelle demande')"
        :description="__('Quelques étapes courtes. Votre brouillon est enregistré à chaque étape : vous pouvez reprendre plus tard.')"
        :back="route('requests.index')"
    />

    @if ($returnComment)
        <flux:callout icon="arrow-uturn-left" color="orange" class="mb-6">
            <flux:callout.heading>{{ __('Demande renvoyée pour correction') }}</flux:callout.heading>
            <flux:callout.text>« {{ $returnComment }} »</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-10">
        {{-- ── Stepper ─────────────────────────────────────────────── --}}
        <nav aria-label="{{ __('Étapes de la demande') }}" class="lg:sticky lg:top-10 lg:self-start">
            {{-- Mobile : barre de progression --}}
            <div class="lg:hidden">
                <div class="flex items-baseline justify-between text-sm">
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ $current['label'] }}</span>
                    <span class="numeric text-zinc-500">{{ __('Étape :n / :total', ['n' => $currentIndex + 1, 'total' => count($keys)]) }}</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10">
                    <div class="h-full rounded-full bg-brand-700 transition-all duration-500 dark:bg-brand-300" style="width: {{ (($currentIndex + 1) / count($keys)) * 100 }}%"></div>
                </div>
            </div>

            {{-- Desktop : liste verticale --}}
            <ol class="relative hidden lg:block">
                @foreach ($this->steps as $key => $item)
                    @php
                        $index = $loop->index;
                        $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'upcoming');
                        $clickable = $index < $currentIndex || ($step === 'review' && $key !== 'review');
                    @endphp
                    <li class="relative pb-7 last:pb-0">
                        @unless ($loop->last)
                            <span @class([
                                'absolute top-9 left-[15px] h-[calc(100%-2.25rem)] w-px',
                                'bg-brand-700 dark:bg-brand-300' => $state === 'done',
                                'bg-zinc-200 dark:bg-white/10' => $state !== 'done',
                            ]) aria-hidden="true"></span>
                        @endunless

                        <button type="button"
                            @if ($clickable) wire:click="goTo('{{ $key }}')" @else disabled @endif
                            @class(['group flex w-full items-start gap-3 text-start', 'cursor-pointer' => $clickable, 'cursor-default' => ! $clickable])
                            @if ($state === 'current') aria-current="step" @endif>
                            <span @class([
                                'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full transition',
                                'bg-brand-800 text-white dark:bg-brand-300 dark:text-brand-950' => $state === 'done',
                                'bg-white text-brand-800 ring-2 ring-brand-800 shadow-[0_0_0_4px_var(--color-brand-100)] dark:bg-zinc-900 dark:text-brand-200 dark:ring-brand-300 dark:shadow-[0_0_0_4px_rgb(156_178_213/0.15)]' => $state === 'current',
                                'bg-white text-zinc-400 ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-white/15' => $state === 'upcoming',
                            ])>
                                @if ($state === 'done')
                                    <flux:icon.check variant="micro" class="size-4" />
                                @else
                                    <flux:icon :icon="$item['icon']" variant="micro" class="size-4" />
                                @endif
                            </span>
                            <span class="min-w-0 pt-0.5">
                                <span @class([
                                    'block text-sm font-medium',
                                    'text-zinc-900 dark:text-white' => $state !== 'upcoming',
                                    'text-zinc-400 dark:text-zinc-500' => $state === 'upcoming',
                                    'group-hover:text-brand-700 dark:group-hover:text-brand-200' => $clickable,
                                ])>{{ $item['label'] }}</span>
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $item['description'] }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>

            @if ($this->beneficiaryUser && $step !== 'beneficiary')
                <div class="mt-8 hidden rounded-xl border border-zinc-200 p-4 lg:block dark:border-white/10">
                    <p class="eyebrow">{{ __('Bénéficiaire') }}</p>
                    <div class="mt-3 flex items-center gap-3">
                        <flux:avatar size="sm" :name="$this->beneficiaryUser->name" :initials="$this->beneficiaryUser->initials()" />
                        <div class="min-w-0 text-sm leading-tight">
                            <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $this->beneficiaryUser->name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $this->beneficiaryUser->category?->getLabel() }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </nav>

        {{-- ── Contenu de l'étape ──────────────────────────────────── --}}
        <form wire:submit="{{ $step === 'review' ? 'submit' : 'next' }}" class="surface flex min-w-0 flex-col">
            <header class="border-b border-zinc-200 px-5 py-5 sm:px-8 dark:border-white/10">
                <p class="eyebrow mb-1 hidden lg:block">{{ __('Étape :n sur :total', ['n' => $currentIndex + 1, 'total' => count($keys)]) }}</p>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                    @switch($step)
                        @case('beneficiary') {{ __('Pour qui est cette demande ?') }} @break
                        @case('subject') {{ __('Que faut-il attribuer ?') }} @break
                        @case('phone') {{ __('Le téléphone') }} @break
                        @case('sim') {{ __('La ligne SIM') }} @break
                        @default {{ __('Vérifiez avant d\'envoyer') }}
                    @endswitch
                </h2>
            </header>

            <div class="flex-1 px-5 py-6 sm:px-8 sm:py-8" wire:key="step-{{ $step }}">
                @include('livewire.requests.wizard.'.$step)
            </div>

            <footer class="flex items-center gap-3 rounded-b-xl border-t border-zinc-200 bg-zinc-50/70 px-5 py-4 sm:px-8 dark:border-white/10 dark:bg-white/[0.02]">
                @if ($currentIndex > 0)
                    <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="previous">
                        {{ __('Précédent') }}
                    </flux:button>
                @endif

                <div class="ms-auto flex items-center gap-4">
                    @if ($savedAt)
                        <span class="hidden items-center gap-1.5 text-xs text-zinc-500 sm:inline-flex" wire:loading.remove>
                            <flux:icon.cloud-arrow-up variant="micro" class="size-4 text-emerald-600" />
                            {{ __('Brouillon enregistré à :time', ['time' => $savedAt]) }}
                        </span>
                    @endif

                    @if ($step === 'review')
                        <flux:button type="submit" variant="primary" icon-trailing="paper-airplane">
                            {{ $isResubmission ? __('Renvoyer la demande') : __('Envoyer la demande') }}
                        </flux:button>
                    @else
                        <flux:button type="submit" variant="primary" icon-trailing="arrow-right">
                            {{ __('Continuer') }}
                        </flux:button>
                    @endif
                </div>
            </footer>
        </form>
    </div>
</div>
