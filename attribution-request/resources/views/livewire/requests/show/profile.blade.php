@php
    $snapshot = $request->beneficiary_snapshot ?? [];
    $profile = $request->profileDetail;
    $person = $request->beneficiary;
    $action = $profile?->action;
@endphp

<section class="surface">
    <header class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
        <flux:icon :icon="$action?->icon() ?? 'user'" variant="micro" class="size-4 text-zinc-400" />
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Information utilisateur') }}</h2>
        @if ($action?->concernsNewcomer())
            <span class="ms-auto rounded-md bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-600/20 ring-inset dark:bg-violet-400/10 dark:text-violet-300">{{ __('Nouvel arrivant') }}</span>
        @endif
    </header>
    <dl class="grid gap-x-8 gap-y-4 px-5 py-5 text-sm sm:grid-cols-3">
        @foreach (array_filter([
            __('Nom') => $request->beneficiaryName(),
            __('Matricule') => $snapshot['matricule'] ?? $person?->matricule ?? __('À attribuer'),
            __('Fonction') => $snapshot['job_title'] ?? $person?->job_title,
            __('Service') => $snapshot['department'] ?? $person?->department?->name,
            __('Location') => $snapshot['site'] ?? $person?->site?->name,
            __('Type de profil') => $profile?->profile_type?->getLabel().($profile?->internship_ends_at ? ' -'.__('fin :date', ['date' => $profile->internship_ends_at->translatedFormat('d/m/Y')]) : ''),
            $action === \App\Enums\ProfileAction::Deletion ? __('Date de départ') : __('Date d\'effet') => $profile?->effective_at?->translatedFormat('d F Y'),
            __('Responsable (N+1)') => $profile?->manager?->name ?? $person?->manager?->name,
            __('Profil de référence') => $profile?->reference_profile,
        ], fn ($value) => $value !== null && $value !== '') as $label => $value)
            <div class="min-w-0">
                <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                <dd class="numeric mt-0.5 truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    <p class="border-t border-zinc-100 px-5 py-3 text-xs text-zinc-500 dark:border-white/5">{{ __('Demandeur : :name', ['name' => $request->requester->name]) }}</p>
</section>

@if ($action?->requiresAccessDetails())
    <section class="surface">
        <header class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
            <flux:icon.key variant="micro" class="size-4 text-zinc-400" />
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Accès et applications') }}</h2>
        </header>
        <div class="space-y-5 px-5 py-5 text-sm">
            <div>
                <p class="text-xs text-zinc-500">{{ __('Accès utilisateur') }}</p>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @forelse ($profile->baseAccessLabels() as $access)
                        <span class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 dark:bg-white/10 dark:text-zinc-200">{{ $access }}</span>
                    @empty
                        <span class="text-zinc-500">{{ __('Aucun') }}</span>
                    @endforelse
                </div>
                @if ($profile->distribution_lists)
                    <p class="mt-2 text-xs text-zinc-500">{{ __('Listes de diffusion : :lists', ['lists' => $profile->distribution_lists]) }}</p>
                @endif
            </div>

            <div>
                <p class="text-xs text-zinc-500">{{ __('Applications business') }}</p>
                <ul class="mt-1.5 divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-white/5 dark:border-white/10">
                    @forelse ($profile->applications as $application)
                        <li class="flex items-center gap-3 px-3 py-2">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $application->name }}</span>
                            @if ($application->needsBusinessApproval())
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-800 dark:bg-amber-400/10 dark:text-amber-300">{{ __('Validation :code', ['code' => $application->ownerDirection->code]) }}</span>
                            @endif
                            <span class="ms-auto text-xs">
                                @if ($application->pivot->executed_at)
                                    <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400"><flux:icon.check-circle variant="micro" class="size-4" /> {{ __('Ouvert') }}</span>
                                @else
                                    <span class="text-zinc-400">{{ __('À traiter') }}</span>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="px-3 py-2 text-zinc-500">{{ __('Aucune') }}</li>
                    @endforelse
                    @if ($profile->other_application)
                        <li class="px-3 py-2"><span class="font-medium text-zinc-900 dark:text-white">{{ $profile->other_application }}</span> <span class="text-xs text-zinc-500">({{ __('autre') }})</span></li>
                    @endif
                </ul>
            </div>

            @if ($profile->equipment?->isNotEmpty() || $profile->gsm_number)
                <div>
                    <p class="text-xs text-zinc-500">{{ __('Matériels') }}</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($profile->equipment ?? [] as $equipment)
                            <span class="inline-flex items-center gap-1 rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-700/15 dark:bg-brand-400/10 dark:text-brand-200">
                                <flux:icon :icon="$equipment->icon()" variant="micro" class="size-3.5" /> {{ $equipment->getLabel() }}
                            </span>
                        @endforeach
                        @if ($profile->gsm_number)
                            <span class="numeric rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-700/15 dark:bg-brand-400/10 dark:text-brand-200">GSM {{ $profile->gsm_number->international() }}</span>
                        @endif
                    </div>
                </div>
            @endif

            @if ($profile->comment)
                <div>
                    <p class="text-xs text-zinc-500">{{ __('Commentaires') }}</p>
                    <p class="mt-1 whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $profile->comment }}</p>
                </div>
            @endif
        </div>
    </section>
@elseif ($profile?->comment)
    <section class="surface px-5 py-4">
        <p class="text-xs text-zinc-500">{{ __('Commentaire') }}</p>
        <p class="mt-1 text-sm whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $profile->comment }}</p>
    </section>
@endif
