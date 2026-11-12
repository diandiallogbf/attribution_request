@php
    $user = auth()->user();
    $hour = now()->hour;
    $greeting = $hour < 12 ? __('Bonjour') : ($hour < 18 ? __('Bon après-midi') : __('Bonsoir'));
@endphp

<div>
    <x-page-header
        :eyebrow="ucfirst(now()->translatedFormat('l j F Y'))"
        :title="$greeting.', '.$user->first_name"
        :description="__('Suivez vos demandes de téléphonie et les équipements qui vous sont attribués.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('requests.create')" wire:navigate>
                {{ __('Nouvelle demande') }}
            </flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($this->pendingApprovals > 0)
        <a href="{{ route('approvals.index') }}" wire:navigate
           class="group mb-6 flex items-center gap-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 transition hover:border-amber-300 dark:border-amber-400/20 dark:bg-amber-400/5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
                <flux:icon.bell-alert class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-amber-900 dark:text-amber-200">
                    {{ trans_choice(':count demande attend votre validation|:count demandes attendent votre validation', $this->pendingApprovals) }}
                </span>
                <span class="text-sm text-amber-800/80 dark:text-amber-200/70">{{ __('Vos collaborateurs attendent votre décision pour avancer.') }}</span>
            </span>
            <span class="hidden items-center gap-1 text-sm font-medium text-amber-900 sm:flex dark:text-amber-200">
                {{ __('Valider') }} <flux:icon.arrow-right variant="micro" class="size-4 transition group-hover:translate-x-0.5" />
            </span>
        </a>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Demandes en cours')" :value="$this->openCount" icon="clock" :href="route('requests.index')"
            :hint="$this->draftCount ? trans_choice(':count brouillon à terminer|:count brouillons à terminer', $this->draftCount) : __('Aucun brouillon en attente')" />
        <x-stat-card :label="__('À valider')" :value="$this->pendingApprovals" icon="check-badge" tone="amber"
            :href="$this->pendingApprovals ? route('approvals.index') : null"
            :hint="$this->pendingApprovals ? __('En attente de votre décision') : __('Rien à valider pour le moment')" />
        <x-stat-card :label="__('Équipements attribués')" :value="$this->equipment->count()" icon="device-phone-mobile" tone="emerald"
            :href="route('equipment.index')" :hint="__('Téléphones et lignes à votre nom')" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        {{-- Demandes récentes --}}
        <section class="surface lg:col-span-2">
            <header class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Demandes récentes') }}</h2>
                @if ($this->recentRequests->isNotEmpty())
                    <flux:link :href="route('requests.index')" wire:navigate class="text-sm" variant="subtle">{{ __('Tout voir') }}</flux:link>
                @endif
            </header>

            @forelse ($this->recentRequests as $request)
                <x-request-row :request="$request" />
            @empty
                <x-empty-state icon="document-plus" :title="__('Aucune demande pour l\'instant')"
                    :description="__('Besoin d\'un téléphone ou d\'une ligne ? La demande prend moins de 3 minutes.')">
                    <flux:button variant="primary" size="sm" icon="plus" :href="route('requests.create')" wire:navigate>{{ __('Faire une demande') }}</flux:button>
                </x-empty-state>
            @endforelse
        </section>

        {{-- Mes équipements --}}
        <section class="surface self-start">
            <header class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Mes équipements') }}</h2>
                <flux:link :href="route('equipment.index')" wire:navigate class="text-sm" variant="subtle">{{ __('Détails') }}</flux:link>
            </header>
            <ul class="divide-y divide-zinc-100 dark:divide-white/5">
                @forelse ($this->equipment as $assignment)
                    @php $item = $assignment->assignable; @endphp
                    <li class="flex items-center gap-3 px-5 py-3.5">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400">
                            <flux:icon :icon="$item instanceof \App\Models\Device ? 'device-phone-mobile' : 'signal'" variant="outline" class="size-5" />
                        </span>
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-medium text-zinc-900 dark:text-white">
                                {{ $item instanceof \App\Models\Device ? $item->model->label : $item->msisdn->international() }}
                            </p>
                            <p class="truncate text-xs text-zinc-500">
                                {{ $item instanceof \App\Models\Device
                                    ? __('Depuis :date', ['date' => $assignment->assigned_at->translatedFormat('F Y')])
                                    : $item->plan_type->getLabel().($item->monthly_recharge_gnf ? ' · '.$item->monthly_recharge_gnf->format() : '') }}
                            </p>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-zinc-500">{{ __('Aucun équipement à votre nom.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
