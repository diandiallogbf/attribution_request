@php
    $heading = match ($step) {
        'beneficiary' => __('Pour qui est cette demande ?'),
        'subject' => __('Que faut-il attribuer ?'),
        'phone' => __('Le téléphone'),
        'sim' => __('La ligne SIM'),
        default => __('Vérifiez avant d\'envoyer'),
    };
@endphp

<x-wizard
    :steps="$this->steps"
    :step="$step"
    :heading="$heading"
    :eyebrow="__('Fiche d\'attribution de téléphone')"
    :title="$isResubmission ? __('Corriger la demande') : __('Nouvelle demande de téléphonie')"
    :description="__('Quelques étapes courtes. Votre brouillon est enregistré à chaque étape : vous pouvez reprendre plus tard.')"
    :back="route('requests.create')"
    :return-comment="$returnComment"
    :saved-at="$savedAt"
    :submit-label="$isResubmission ? __('Renvoyer la demande') : __('Envoyer la demande')">
    @if ($this->beneficiaryUser && $step !== 'beneficiary')
        <x-slot:aside>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                <p class="eyebrow">{{ __('Bénéficiaire') }}</p>
                <div class="mt-3 flex items-center gap-3">
                    <flux:avatar size="sm" :name="$this->beneficiaryUser->name" :initials="$this->beneficiaryUser->initials()" />
                    <div class="min-w-0 text-sm leading-tight">
                        <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $this->beneficiaryUser->name }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ $this->beneficiaryUser->category?->getLabel() }}</p>
                    </div>
                </div>
            </div>
        </x-slot:aside>
    @endif

    @include('livewire.requests.wizard.'.$step)
</x-wizard>
