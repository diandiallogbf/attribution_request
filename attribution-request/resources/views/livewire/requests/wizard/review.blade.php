@use('App\Enums\LineOption')
@use('App\Enums\PhoneReason')
@use('App\Enums\PhoneType')
@use('App\Enums\PlanType')
@use('App\Enums\SimAction')
@use('App\ValueObjects\PhoneNumber')

@php
    $person = $this->beneficiaryUser;
    $check = $this->allowanceCheck;
    $approver = $this->approver;
    $selfApproved = $approver && $approver->id === auth()->id();

    $reason = PhoneReason::tryFrom((string) $phone->reason);
    $type = PhoneType::tryFrom((string) $phone->phoneType);
    $previousDevice = ctype_digit((string) $phone->previousDeviceChoice)
        ? $this->beneficiaryDevices->firstWhere('assignable_id', (int) $phone->previousDeviceChoice)?->assignable->model->label
        : trim($phone->previousDeviceLabel.' '.$phone->previousDeviceSerial);

    $action = SimAction::tryFrom((string) $sim->action);
    $plan = PlanType::tryFrom((string) $sim->planType);
    $line = ctype_digit((string) $sim->existingLineChoice)
        ? $this->beneficiaryLines->firstWhere('assignable_id', (int) $sim->existingLineChoice)?->assignable->msisdn->international()
        : PhoneNumber::tryFrom($sim->existingMsisdn)?->international();
    $options = collect($sim->options)->map(fn ($o) => LineOption::tryFrom($o))->filter();
    $recharge = ctype_digit((string) $sim->monthlyRecharge) ? \App\ValueObjects\MoneyGnf::from((int) $sim->monthlyRecharge) : null;

    $sections = [
        'beneficiary' => [__('Bénéficiaire'), 'user', [
            __('Nom') => $person?->name,
            __('Matricule') => $person?->matricule,
            __('Fonction') => $person?->job_title,
            __('Service') => $person?->department?->name,
            __('Catégorie') => $person?->category?->getLabel(),
            __('Site') => $person?->site?->name,
        ]],
    ];

    if ($subject->wantsPhone) {
        $sections['phone'] = [__('Téléphone'), 'device-phone-mobile', array_filter([
            __('Motif') => $reason?->getLabel(),
            __('Type souhaité') => $reason?->requiresNewDevice() ? ($type === PhoneType::Other ? $phone->otherTypeLabel : $type?->getLabel()) : null,
            __('Appareil concerné') => $reason?->requiresPreviousDevice() ? ($previousDevice ?: '—') : null,
            __('Motif détaillé') => $phone->detailedReason,
        ], fn ($v) => $v !== null)];
    }

    if ($subject->wantsSim) {
        $sections['sim'] = [__('Ligne SIM'), 'signal', array_filter([
            __('Action') => $action?->getLabel(),
            __('Ligne concernée') => $action?->requiresExistingLine() ? ($line ?? '—') : null,
            __('Forfait') => $action?->requiresPlan() ? $plan?->getLabel() : null,
            __('Options') => $action?->requiresPlan() ? ($options->isEmpty() ? __('Aucune') : $options->map(fn ($o) => $o->getLabel().' ('.$o->code().')')->join(', ')) : null,
            __('Recharge mensuelle') => $action?->requiresPlan() ? $recharge?->format() : null,
            __('Entité') => $sim->entity ?: null,
        ], fn ($v) => $v !== null)];
    }
@endphp

<div class="space-y-8">
    {{-- Récapitulatif --}}
    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-white/10 dark:border-white/10">
        @foreach ($sections as $key => [$title, $icon, $rows])
            <section class="px-5 py-5">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                        <flux:icon :icon="$icon" variant="micro" class="size-4 text-zinc-400" />
                        {{ $title }}
                    </h3>
                    @if ($key !== 'beneficiary' || $requestId === null)
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="goTo('{{ $key }}')">{{ __('Modifier') }}</flux:button>
                    @endif
                </div>
                <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ($rows as $label => $value)
                        <div @class(['min-w-0', 'sm:col-span-2' => $label === __('Motif détaillé')])>
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                            <dd class="numeric mt-0.5 font-medium break-words text-zinc-900 dark:text-zinc-100 {{ $label === __('Motif détaillé') ? 'font-normal whitespace-pre-line' : '' }}">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>

    {{-- Conformité à la grille de dotation --}}
    @if ($check)
        @if (! $check->isCompliant())
            <flux:callout icon="scale" color="amber">
                <flux:callout.heading>{{ __('Hors grille de dotation') }}</flux:callout.heading>
                <flux:callout.text>
                    <ul class="mt-1 list-disc space-y-1 ps-4">
                        @foreach ($check->violations as $violation)<li>{{ $violation }}</li>@endforeach
                    </ul>
                    <p class="mt-2">{{ __('La demande peut être envoyée : la DSI décidera d\'une éventuelle dérogation.') }}</p>
                </flux:callout.text>
            </flux:callout>
        @elseif (! $check->hasWarnings())
            <flux:callout icon="check-badge" color="emerald">
                <flux:callout.text>{{ __('Conforme à la grille de dotation de la catégorie :category.', ['category' => $person?->category?->getLabel()]) }}</flux:callout.text>
            </flux:callout>
        @endif
        @foreach ($check->warnings as $warning)
            <flux:callout icon="information-circle" color="sky">
                <flux:callout.text>{{ $warning }}</flux:callout.text>
            </flux:callout>
        @endforeach
    @endif

    {{-- Circuit de validation --}}
    <x-form-section :title="__('Après l\'envoi')">
        <ol class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['user-circle', __('Validation manager'), $selfApproved ? __('Validée d\'office (vous êtes le manager)') : ($approver?->name ?? __('Manager non défini')), $selfApproved],
                ['shield-check', __('Approbation DSI'), __('Contrôle de la grille, du stock et du modèle'), false],
                ['truck', __('Commande & remise'), __('Vous serez notifié à chaque étape'), false],
            ] as $i => [$icon, $label, $detail, $done])
                <li class="flex gap-3 rounded-xl bg-zinc-50 p-3.5 dark:bg-white/[0.03]">
                    <span @class([
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $done,
                        'bg-white text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-white/10' => ! $done,
                    ])>
                        @if ($done) <flux:icon.check variant="micro" class="size-4" /> @else {{ $i + 1 }} @endif
                    </span>
                    <span class="min-w-0 text-sm leading-tight">
                        <span class="block font-medium text-zinc-900 dark:text-white">{{ $label }}</span>
                        <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ $detail }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </x-form-section>

    <x-form-section :title="__('Commentaire pour les valideurs')" optional>
        <flux:textarea wire:model="comment" rows="2" :placeholder="__('Information utile pour le manager ou la DSI…')" />
    </x-form-section>

    {{-- Engagement (mention officielle de la fiche F-0015) --}}
    <div>
        <x-choice-card type="checkbox" wire:model="policyAccepted" icon="document-check"
            :title="__('Je m\'engage sans réserve à respecter la procédure de gestion de la téléphonie.')"
            :description="__('Votre acceptation est horodatée et figurera sur la fiche officielle.')" />
        <x-field-error name="policyAccepted" />
    </div>
</div>
