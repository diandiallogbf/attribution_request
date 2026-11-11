@use('App\Enums\ApprovalDecision')
@use('App\Enums\RequestStatus')

@php
    $request = $this->serviceRequest;
    $status = $request->status;
    $snapshot = $request->beneficiary_snapshot;
    $person = $request->beneficiary;
    $phone = $request->phoneDetail;
    $sim = $request->simDetail;
    $check = $this->allowanceCheck;
    $canDecide = auth()->user()->can('decide', $request);
    $canEdit = auth()->user()->can('update', $request);
    $canCancel = auth()->user()->can('cancel', $request);
    $lastDecision = $request->approvals->whereIn('decision', [ApprovalDecision::Returned, ApprovalDecision::Rejected])->sortByDesc('decided_at')->first();
    $categoryLabel = isset($snapshot['category'])
        ? \App\Enums\EmployeeCategory::tryFrom($snapshot['category'])?->getLabel()
        : $person->category?->getLabel();
@endphp

<div>
    @if (session('submitted'))
        <div class="mb-6 flex items-start gap-4 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-400/20 dark:bg-emerald-400/5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300">
                <flux:icon.paper-airplane class="size-5" />
            </span>
            <div>
                <p class="font-semibold text-emerald-900 dark:text-emerald-200">{{ __('Demande envoyée') }}</p>
                <p class="text-sm text-emerald-800/80 dark:text-emerald-200/70">{{ session('submitted') }} {{ __('Vous serez notifié par e-mail à chaque étape.') }}</p>
            </div>
        </div>
    @endif

    <x-page-header :back="route('requests.index')"
        :eyebrow="__('Demande de téléphonie').' · '.$request->displayReference()"
        :title="$request->subjectLabel().($request->beneficiary_id !== auth()->id() ? ' — '.$person->name : '')">
        <x-slot:actions>
            <x-status-badge :status="$status" size="md" />
            @if ($canEdit)
                <flux:button size="sm" icon="pencil-square" :href="route('requests.edit', $request)" wire:navigate>
                    {{ $status === RequestStatus::Returned ? __('Corriger') : __('Reprendre') }}
                </flux:button>
            @endif
            @if ($canCancel)
                <flux:modal.trigger name="cancel">
                    <flux:button size="sm" variant="ghost" icon="x-mark">{{ __('Annuler') }}</flux:button>
                </flux:modal.trigger>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Décision attendue --}}
    @if ($canDecide)
        <section class="mb-6 overflow-hidden rounded-xl border border-brand-200 bg-linear-to-r from-brand-50 to-white shadow-(--shadow-card) dark:border-brand-300/20 dark:from-brand-400/10 dark:to-zinc-900">
            <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-800 text-white dark:bg-brand-300 dark:text-brand-950">
                    <flux:icon.check-badge class="size-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Votre décision est attendue') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $request->pendingApproval()?->step->getLabel() }} ·
                        {{ __('soumise par :name :ago', ['name' => $request->requester->name, 'ago' => $request->submitted_at?->diffForHumans()]) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="openDecision('returned')">{{ __('Renvoyer') }}</flux:button>
                    <flux:button size="sm" variant="danger" icon="x-mark" wire:click="openDecision('rejected')">{{ __('Rejeter') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="check" wire:click="openDecision('approved')">{{ __('Valider') }}</flux:button>
                </div>
            </div>
        </section>
    @endif

    @if ($lastDecision && in_array($status, [RequestStatus::Returned, RequestStatus::Rejected], true))
        <flux:callout :icon="$status === RequestStatus::Returned ? 'arrow-uturn-left' : 'x-circle'" :color="$status === RequestStatus::Returned ? 'orange' : 'red'" class="mb-6">
            <flux:callout.heading>
                {{ $status === RequestStatus::Returned ? __('Renvoyée pour correction par :name', ['name' => $lastDecision->decidedBy?->name]) : __('Rejetée par :name', ['name' => $lastDecision->decidedBy?->name]) }}
            </flux:callout.heading>
            <flux:callout.text>« {{ $lastDecision->comment }} »</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Avancement --}}
    <section class="surface mb-6 px-5 py-5">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Avancement') }}</h2>
            @if ($request->submitted_at)
                <p class="text-xs text-zinc-500">{{ __('Soumise le :date', ['date' => $request->submitted_at->translatedFormat('d F Y à H:i')]) }}</p>
            @endif
        </div>
        <x-request-progress :request="$request" />
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Bénéficiaire --}}
            <section class="surface">
                <header class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                    <flux:icon.user variant="micro" class="size-4 text-zinc-400" />
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Information de l\'utilisateur') }}</h2>
                </header>
                <dl class="grid gap-x-8 gap-y-4 px-5 py-5 text-sm sm:grid-cols-3">
                    @foreach ([
                        __('Nom') => $person->name,
                        __('Matricule') => $snapshot['matricule'] ?? $person->matricule,
                        __('Catégorie') => $categoryLabel,
                        __('Fonction') => $snapshot['job_title'] ?? $person->job_title,
                        __('Service') => $snapshot['department'] ?? $person->department?->name,
                        __('Site') => $snapshot['site'] ?? $person->site?->name,
                    ] as $label => $value)
                        <div class="min-w-0">
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                            <dd class="numeric mt-0.5 truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($request->requester_id !== $request->beneficiary_id)
                    <p class="border-t border-zinc-100 px-5 py-3 text-xs text-zinc-500 dark:border-white/5">{{ __('Demande initiée par :name', ['name' => $request->requester->name]) }}</p>
                @endif
            </section>

            {{-- Téléphone --}}
            @if ($phone)
                <section class="surface">
                    <header class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                        <flux:icon.device-phone-mobile variant="micro" class="size-4 text-zinc-400" />
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Information téléphone') }}</h2>
                        @if ($phone->reason?->requiresNewDevice() && $phone->requested_phone_type && $check->rule)
                            @php $inGrid = $check->rule->allowsPhoneType($phone->requested_phone_type); @endphp
                            <span @class([
                                'ms-auto rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset',
                                'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300' => $inGrid,
                                'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300' => ! $inGrid,
                            ])>{{ $inGrid ? __('Conforme à la dotation') : __('Hors grille') }}</span>
                        @endif
                    </header>
                    <dl class="grid gap-x-8 gap-y-4 px-5 py-5 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-zinc-500">{{ __('Motif') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $phone->reason?->getLabel() ?? '—' }}</dd></div>
                        @if ($phone->reason?->requiresNewDevice())
                            <div><dt class="text-xs text-zinc-500">{{ __('Type souhaité') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $phone->requestedTypeLabel() ?? '—' }}</dd></div>
                        @endif
                        @if ($phone->reason?->requiresPreviousDevice())
                            <div><dt class="text-xs text-zinc-500">{{ __('Appareil concerné') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $phone->previousDevice?->label ?? trim($phone->previous_device_label.' '.$phone->previous_device_serial) ?: '—' }}</dd></div>
                        @endif
                        @if ($phone->approvedDeviceModel)
                            <div><dt class="text-xs text-zinc-500">{{ __('Modèle retenu par la DSI') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $phone->approvedDeviceModel->label }}</dd></div>
                        @endif
                        <div class="sm:col-span-3"><dt class="text-xs text-zinc-500">{{ __('Motif détaillé') }}</dt><dd class="mt-1 whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $phone->detailed_reason ?: '—' }}</dd></div>
                    </dl>
                </section>
            @endif

            {{-- Ligne SIM --}}
            @if ($sim)
                <section class="surface">
                    <header class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                        <flux:icon.signal variant="micro" class="size-4 text-zinc-400" />
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Information numéro (SIM)') }}</h2>
                        @if ($sim->requested_monthly_recharge_gnf && $check->rule)
                            @php $okRecharge = $check->rule->allowsRecharge($sim->requested_monthly_recharge_gnf); @endphp
                            <span @class([
                                'ms-auto rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset',
                                'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300' => $okRecharge,
                                'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300' => ! $okRecharge,
                            ])>{{ $okRecharge ? __('Recharge conforme') : __('Recharge hors plafond') }}</span>
                        @endif
                    </header>
                    <dl class="grid gap-x-8 gap-y-4 px-5 py-5 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-zinc-500">{{ __('Action') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $sim->action?->getLabel() ?? '—' }}</dd></div>
                        @if ($sim->action?->requiresExistingLine())
                            <div><dt class="text-xs text-zinc-500">{{ __('Ligne concernée') }}</dt><dd class="numeric mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $sim->existingSimLine?->msisdn->international() ?? $sim->existing_msisdn?->international() ?? '—' }}</dd></div>
                        @endif
                        @if ($sim->action?->requiresPlan())
                            <div><dt class="text-xs text-zinc-500">{{ __('Forfait') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $sim->plan_type?->getLabel() ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">{{ __('Recharge mensuelle') }}</dt><dd class="numeric mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $sim->requested_monthly_recharge_gnf?->format() ?? '—' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-zinc-500">{{ __('Options') }}</dt>
                                <dd class="mt-1 flex flex-wrap gap-1.5">
                                    @forelse ($sim->options ?? [] as $option)
                                        <span class="rounded-md bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-800 ring-1 ring-brand-700/15 dark:bg-brand-400/10 dark:text-brand-200">{{ $option->code() }} · {{ $option->getLabel() }}</span>
                                    @empty
                                        <span class="text-zinc-500">{{ __('Aucune') }}</span>
                                    @endforelse
                                </dd>
                            </div>
                        @endif
                        @if ($sim->entity)
                            <div><dt class="text-xs text-zinc-500">{{ __('Entité') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $sim->entity }}</dd></div>
                        @endif
                    </dl>
                </section>
            @endif

            @if ($request->comment)
                <section class="surface px-5 py-4">
                    <p class="text-xs text-zinc-500">{{ __('Commentaire du demandeur') }}</p>
                    <p class="mt-1 text-sm whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $request->comment }}</p>
                </section>
            @endif
        </div>

        {{-- Colonne latérale --}}
        <aside class="space-y-6">
            <section class="surface">
                <header class="border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Validations') }}</h2>
                </header>
                <ul class="divide-y divide-zinc-100 dark:divide-white/5">
                    @forelse ($request->approvals->sortBy('id') as $approval)
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <x-status-badge :status="$approval->decision" class="mt-0.5" />
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $approval->step->getLabel() }}</p>
                                <p class="truncate text-xs text-zinc-500">
                                    @if ($approval->decidedBy)
                                        {{ $approval->decidedBy->name }} · {{ $approval->decided_at?->translatedFormat('d M Y, H:i') }}
                                    @else
                                        {{ $approval->assignedTo?->name ?? __('Équipe DSI') }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-zinc-500">{{ __('Aucune validation pour l\'instant.') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="surface">
                <header class="border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Historique') }}</h2>
                </header>
                <ol class="px-5 py-5">
                    @foreach ($request->events->reverse() as $event)
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @unless ($loop->last)
                                <span class="absolute top-7 left-[13px] h-[calc(100%-1.75rem)] w-px bg-zinc-200 dark:bg-white/10" aria-hidden="true"></span>
                            @endunless
                            <span @class([
                                'relative flex size-7 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-zinc-900',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $event->type->getColor() === 'success',
                                'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-300' => $event->type->getColor() === 'orange',
                                'bg-red-100 text-red-700 dark:bg-red-400/15 dark:text-red-300' => $event->type->getColor() === 'danger',
                                'bg-violet-100 text-violet-700 dark:bg-violet-400/15 dark:text-violet-300' => $event->type->getColor() === 'violet',
                                'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300' => $event->type->getColor() === 'gray',
                            ])>
                                <flux:icon :icon="$event->type->icon()" variant="micro" class="size-3.5" />
                            </span>
                            <div class="min-w-0 pt-0.5 text-sm">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $event->type->getLabel() }}</p>
                                <p class="text-xs text-zinc-500">
                                    {{ $event->actor?->name ?? __('Système') }} ·
                                    <time datetime="{{ $event->created_at->toIso8601String() }}" title="{{ $event->created_at->translatedFormat('d F Y à H:i') }}">{{ $event->created_at->diffForHumans() }}</time>
                                </p>
                                @if ($event->comment)
                                    <p class="mt-1.5 rounded-lg bg-zinc-50 px-3 py-2 text-[13px] text-zinc-700 dark:bg-white/5 dark:text-zinc-300">{{ $event->comment }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>

    {{-- Modale de décision --}}
    <flux:modal name="decision" class="w-full max-w-lg">
        @php $decision = ApprovalDecision::tryFrom((string) $pendingDecision); @endphp
        <form wire:submit="decide" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @switch($decision)
                        @case(ApprovalDecision::Approved) {{ __('Valider la demande :ref', ['ref' => $request->displayReference()]) }} @break
                        @case(ApprovalDecision::Returned) {{ __('Renvoyer pour correction') }} @break
                        @case(ApprovalDecision::Rejected) {{ __('Rejeter la demande') }} @break
                    @endswitch
                </flux:heading>
                <flux:text class="mt-2">
                    @switch($decision)
                        @case(ApprovalDecision::Approved) {{ __('La demande passera à l\'étape suivante et le demandeur sera notifié. Votre validation est horodatée.') }} @break
                        @case(ApprovalDecision::Returned) {{ __('Le demandeur pourra modifier sa demande puis la soumettre à nouveau.') }} @break
                        @case(ApprovalDecision::Rejected) {{ __('La demande sera définitivement close. Cette action est irréversible.') }} @break
                    @endswitch
                </flux:text>
            </div>

            <flux:textarea wire:model="decisionComment" rows="3"
                :label="$decision?->requiresComment() ? __('Motif (obligatoire)') : __('Commentaire (facultatif)')"
                :placeholder="$decision === ApprovalDecision::Returned ? __('Ex. : précisez le modèle du téléphone à remplacer.') : ''" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Annuler') }}</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$decision === ApprovalDecision::Rejected ? 'danger' : 'primary'">
                    {{ __('Confirmer') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modale d'annulation --}}
    <flux:modal name="cancel" class="w-full max-w-md">
        <form wire:submit="cancel" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Annuler cette demande ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Les validations en cours seront interrompues. Vous pourrez créer une nouvelle demande à tout moment.') }}</flux:text>
            </div>
            <flux:input wire:model="cancelReason" :label="__('Raison')" :badge="__('Facultatif')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Retour') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Annuler la demande') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
