@php
    $snapshot = $request->beneficiary_snapshot;
    $person = $request->beneficiary;
    $phone = $request->phoneDetail;
    $sim = $request->simDetail;
    $check = $this->allowanceCheck;
    $categoryLabel = isset($snapshot['category'])
        ? \App\Enums\EmployeeCategory::tryFrom($snapshot['category'])?->getLabel()
        : $person?->category?->getLabel();
    $badge = fn (bool $ok, string $yes, string $no) => '<span class="ms-auto rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset '
        .($ok ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300')
        .'">'.e($ok ? $yes : $no).'</span>';
@endphp

<section class="surface">
    <header class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
        <flux:icon.user variant="micro" class="size-4 text-zinc-400" />
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Information de l\'utilisateur') }}</h2>
    </header>
    <dl class="grid gap-x-8 gap-y-4 px-5 py-5 text-sm sm:grid-cols-3">
        @foreach ([
            __('Nom') => $request->beneficiaryName(),
            __('Matricule') => $snapshot['matricule'] ?? $person?->matricule,
            __('Catégorie') => $categoryLabel,
            __('Fonction') => $snapshot['job_title'] ?? $person?->job_title,
            __('Service') => $snapshot['department'] ?? $person?->department?->name,
            __('Site') => $snapshot['site'] ?? $person?->site?->name,
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

@if ($phone)
    <section class="surface">
        <header class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
            <flux:icon.device-phone-mobile variant="micro" class="size-4 text-zinc-400" />
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Information téléphone') }}</h2>
            @if ($phone->reason?->requiresNewDevice() && $phone->requested_phone_type && $check?->rule)
                {!! $badge($check->rule->allowsPhoneType($phone->requested_phone_type), __('Conforme à la dotation'), __('Hors grille')) !!}
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

@if ($sim)
    <section class="surface">
        <header class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
            <flux:icon.signal variant="micro" class="size-4 text-zinc-400" />
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Information numéro (SIM)') }}</h2>
            @if ($sim->requested_monthly_recharge_gnf && $check?->rule)
                {!! $badge($check->rule->allowsRecharge($sim->requested_monthly_recharge_gnf), __('Recharge conforme'), __('Recharge hors plafond')) !!}
            @endif
        </header>
        <dl class="grid gap-x-8 gap-y-4 px-5 py-5 text-sm sm:grid-cols-3">
            <div><dt class="text-xs text-zinc-500">{{ __('Action') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $sim->action?->getLabel() ?? '—' }}</dd></div>
            @if ($sim->action?->requiresExistingLine())
                <div><dt class="text-xs text-zinc-500">{{ __('Ligne concernée') }}</dt><dd class="numeric mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $sim->existingSimLine?->msisdn->international() ?? $sim->existing_msisdn?->international() ?? '—' }}</dd></div>
            @endif
            @if ($sim->action?->requiresPlan())
                <div><dt class="text-xs text-zinc-500">{{ __('Forfait') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $sim->plan_type?->getLabel() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-zinc-500">{{ __('Recharge mensuelle') }}</dt>
                    <dd class="numeric mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">
                        {{ ($sim->approved_monthly_recharge_gnf ?? $sim->requested_monthly_recharge_gnf)?->format() ?? '—' }}
                        @if ($sim->approved_monthly_recharge_gnf && ! $sim->approved_monthly_recharge_gnf->equals($sim->requested_monthly_recharge_gnf ?? \App\ValueObjects\MoneyGnf::zero()))
                            <span class="block text-xs font-normal text-zinc-500">{{ __('demandé : :amount', ['amount' => $sim->requested_monthly_recharge_gnf?->format()]) }}</span>
                        @endif
                    </dd>
                </div>
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
