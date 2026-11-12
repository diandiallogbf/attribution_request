@use('App\Enums\PhoneReason')
@use('App\Enums\PhoneType')

@php
    $reason = $phone->reasonEnum();
    $category = $this->beneficiaryUser?->category;
    $allowed = $this->allowedPhoneTypes;
    $check = $reason?->requiresNewDevice() ? $this->allowanceCheck : null;
    $placeholder = match ($reason) {
        PhoneReason::Acquisition => __('Ex. : prise de poste comme superviseur de quai, doit être joignable en permanence par l\'équipe.'),
        PhoneReason::Replacement => __('Ex. : écran cassé suite à une chute sur le quai, le téléphone ne s\'allume plus.'),
        PhoneReason::Repair => __('Ex. : la batterie ne tient plus que deux heures.'),
        PhoneReason::Return => __('Ex. : départ de l\'entreprise le 30 novembre.'),
        default => '',
    };
@endphp

<div class="space-y-9">
    <x-form-section :title="__('Motif de la demande')">
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach (PhoneReason::cases() as $case)
                <x-choice-card name="phone-reason" value="{{ $case->value }}" wire:model.live="phone.reason"
                    :icon="$case->icon()" :title="$case->getLabel()" :description="$case->description()" />
            @endforeach
        </div>
        <x-field-error name="phone.reason" />
    </x-form-section>

    @if ($reason?->requiresPreviousDevice())
        <x-form-section :title="__('Téléphone concerné')" :description="__('Sélectionnez l\'appareil actuellement attribué.')">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($this->beneficiaryDevices as $assignment)
                    <x-choice-card size="sm" name="previous-device" value="{{ $assignment->assignable_id }}" wire:model.live="phone.previousDeviceChoice"
                        icon="device-phone-mobile"
                        :title="$assignment->assignable->model->label"
                        :description="($assignment->assignable->imei ? 'IMEI '.$assignment->assignable->imei->formatted().' · ' : '').__('depuis :date', ['date' => $assignment->assigned_at->translatedFormat('M Y')])" />
                @endforeach
                <x-choice-card size="sm" name="previous-device" value="other" wire:model.live="phone.previousDeviceChoice"
                    icon="question-mark-circle" :title="__('Un autre appareil')" :description="__('Non répertorié dans le parc')" />
            </div>
            <x-field-error name="phone.previousDeviceChoice" />

            @if ($phone->previousDeviceChoice === 'other')
                <div class="mt-4 grid gap-4 rounded-xl border border-dashed border-zinc-300 p-4 sm:grid-cols-2 dark:border-white/15">
                    <flux:input wire:model.blur="phone.previousDeviceLabel" :label="__('Modèle')" placeholder="Samsung Galaxy A14" />
                    <flux:input wire:model.blur="phone.previousDeviceSerial" :label="__('N° de série ou IMEI')" :badge="__('Facultatif')" />
                </div>
            @endif
        </x-form-section>
    @endif

    @if ($reason?->requiresNewDevice())
        <x-form-section :title="__('Type de téléphone souhaité')"
            :description="$category ? __('Grille de dotation « :category » : :types.', [
                'category' => $category->getLabel(),
                'types' => $allowed->map->getLabel()->join(', ', ' et '),
            ]) : null">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (PhoneType::cases() as $type)
                    @php $inGrid = $type === PhoneType::Other ? null : $allowed->contains($type); @endphp
                    <x-choice-card size="sm" name="phone-type" value="{{ $type->value }}" wire:model.live="phone.phoneType"
                        :icon="$type->icon()" :title="$type->getLabel()" :description="$type->description()"
                        :badge="$inGrid === null ? null : ($inGrid ? __('Dotation') : __('Hors grille'))"
                        :badge-tone="$inGrid ? 'emerald' : 'amber'" />
                @endforeach
            </div>
            <x-field-error name="phone.phoneType" />

            @if ($phone->phoneType === PhoneType::Other->value)
                <div class="mt-4">
                    <flux:input wire:model.blur="phone.otherTypeLabel" :label="__('Précisez l\'appareil souhaité')" />
                </div>
            @endif

            @if ($check && ! $check->isCompliant())
                <flux:callout icon="scale" color="amber" class="mt-4">
                    <flux:callout.text>{{ __('Ce choix sort de la grille de dotation : la DSI l\'examinera. Justifiez-le dans le motif détaillé ci-dessous.') }}</flux:callout.text>
                </flux:callout>
            @endif

            @foreach ($check?->warnings ?? [] as $warning)
                <flux:callout icon="calendar-days" color="sky" class="mt-4">
                    <flux:callout.text>{{ $warning }}</flux:callout.text>
                </flux:callout>
            @endforeach
        </x-form-section>
    @endif

    @if ($reason)
        <x-form-section :title="__('Motif détaillé')" :description="__('Obligatoire. Décrivez le besoin ou le problème rencontré : c\'est ce que liront votre manager et la DSI.')">
            <div x-data class="relative">
                <flux:textarea wire:model="phone.detailedReason" rows="4" resize="vertical" :placeholder="$placeholder" />
                <span class="numeric pointer-events-none absolute right-3 bottom-2.5 text-xs text-zinc-400"
                      x-text="($wire.phone.detailedReason || '').length + ' / 1000'"></span>
            </div>
        </x-form-section>
    @endif
</div>
