@use('App\Enums\LineOption')
@use('App\Enums\PlanType')
@use('App\Enums\SimAction')

@php
    $action = $sim->actionEnum();
    $max = $this->maxRecharge;
@endphp

<div class="space-y-9">
    <x-form-section :title="__('Action sur la ligne')">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (SimAction::cases() as $case)
                <x-choice-card size="sm" name="sim-action" value="{{ $case->value }}" wire:model.live="sim.action"
                    :icon="$case->icon()" :title="$case->getLabel()" :description="$case->description()" />
            @endforeach
        </div>
        <x-field-error name="sim.action" />
    </x-form-section>

    @if ($action?->requiresExistingLine())
        <x-form-section :title="__('Ligne concernée')">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($this->beneficiaryLines as $assignment)
                    <x-choice-card size="sm" name="existing-line" value="{{ $assignment->assignable_id }}" wire:model.live="sim.existingLineChoice"
                        icon="phone"
                        :title="$assignment->assignable->msisdn->international()"
                        :description="$assignment->assignable->plan_type->getLabel().($assignment->assignable->monthly_recharge_gnf ? ' · '.$assignment->assignable->monthly_recharge_gnf->format().' / mois' : '')" />
                @endforeach
                <x-choice-card size="sm" name="existing-line" value="other" wire:model.live="sim.existingLineChoice"
                    icon="hashtag" :title="__('Un autre numéro')"
                    :description="$action === SimAction::Transfer ? __('Ligne actuellement attribuée à quelqu\'un d\'autre') : __('Saisir le numéro')" />
            </div>
            <x-field-error name="sim.existingLineChoice" />

            @if ($sim->existingLineChoice === 'other')
                <div class="mt-4 max-w-xs">
                    <flux:input wire:model.blur="sim.existingMsisdn" :label="__('Numéro')" icon="phone" placeholder="622 12 34 56"
                        inputmode="tel" autocomplete="off" :description="__('Mobile guinéen, 9 chiffres')" />
                </div>
            @endif
        </x-form-section>
    @endif

    @if ($action?->requiresPlan())
        <x-form-section :title="__('Type de forfait')">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (PlanType::cases() as $plan)
                    <x-choice-card size="sm" name="plan-type" value="{{ $plan->value }}" wire:model.live="sim.planType"
                        :icon="$plan === PlanType::Postpaid ? 'document-text' : 'credit-card'"
                        :title="$plan->getLabel()" :description="$plan->description()" />
                @endforeach
            </div>
            <x-field-error name="sim.planType" />
        </x-form-section>

        <x-form-section :title="__('Options de la ligne')" optional>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach (LineOption::cases() as $option)
                    <x-choice-card size="sm" type="checkbox" value="{{ $option->value }}" wire:model.live="sim.options"
                        :title="$option->getLabel()" :badge="$option->code()" badge-tone="brand" />
                @endforeach
            </div>
        </x-form-section>

        <x-form-section :title="__('Recharge mensuelle')"
            :description="$max ? __('Plafond de la dotation :category : :max par mois.', ['category' => $this->beneficiaryUser->category->getLabel(), 'max' => $max->format()]) : null">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" role="radiogroup">
                @foreach ($this->rechargeAmounts as $amount)
                    @php $over = $max && $amount->amount_gnf->isGreaterThan($max); @endphp
                    <label @class([
                        'group relative flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2.5 text-sm transition',
                        'border-zinc-200 bg-white hover:border-zinc-300 dark:border-white/10 dark:bg-white/[0.03]',
                        'has-checked:border-brand-700 has-checked:bg-brand-800 has-checked:text-white dark:has-checked:border-brand-300 dark:has-checked:bg-brand-300 dark:has-checked:text-brand-950',
                        'has-focus-visible:ring-2 has-focus-visible:ring-brand-500/40',
                    ])>
                        <input type="radio" name="recharge" value="{{ $amount->amount_gnf->amount }}" wire:model.live="sim.monthlyRecharge" class="sr-only">
                        <span class="numeric font-medium">{{ $amount->amount_gnf->format() }}</span>
                        @if ($over)
                            <span class="size-1.5 rounded-full bg-amber-500 group-has-checked:bg-amber-300" title="{{ __('Au-delà du plafond') }}"></span>
                        @endif
                    </label>
                @endforeach
            </div>
            <x-field-error name="sim.monthlyRecharge" />

            @if ($max && ctype_digit((string) $sim->monthlyRecharge) && (int) $sim->monthlyRecharge > $max->amount)
                <flux:callout icon="scale" color="amber" class="mt-4">
                    <flux:callout.text>{{ __('Ce montant dépasse le plafond de la dotation : il sera soumis à l\'arbitrage de la DSI.') }}</flux:callout.text>
                </flux:callout>
            @else
                <p class="mt-3 flex items-center gap-1.5 text-xs text-zinc-500">
                    <span class="size-1.5 rounded-full bg-amber-500"></span> {{ __('Au-delà du plafond de votre catégorie') }}
                </p>
            @endif
        </x-form-section>
    @endif

    @if ($action)
        <x-form-section :title="__('Entité')" optional :description="__('À préciser en cas de transfert entre entités ou de facturation particulière.')">
            <div class="max-w-sm">
                <flux:input wire:model.blur="sim.entity" placeholder="Conakry Terminal" />
            </div>
        </x-form-section>
    @endif
</div>
