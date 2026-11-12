@php
    $action = $profile->actionEnum();
    $heading = match ($step) {
        'action' => __('Quel est le besoin ?'),
        'person' => $action?->concernsNewcomer() ? __('Le nouveau collaborateur') : __('Le collaborateur concerné'),
        'access' => __('Accès utilisateur'),
        'applications' => __('Applications métier'),
        'equipment' => __('Matériel informatique'),
        default => __('Vérifiez avant d\'envoyer'),
    };
@endphp

<x-wizard
    :steps="$this->steps"
    :step="$step"
    :heading="$heading"
    :eyebrow="__('Fiche profil utilisateur')"
    :title="$isResubmission ? __('Corriger la fiche profil') : __('Nouvelle fiche profil')"
    :description="__('Ouverture, modification ou fermeture des accès informatiques d\'un collaborateur.')"
    :back="route('requests.create')"
    :return-comment="$returnComment"
    :saved-at="$savedAt"
    :submit-label="$isResubmission ? __('Renvoyer la fiche') : __('Envoyer la fiche')">
    @if ($action && $step !== 'action')
        <x-slot:aside>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                <p class="eyebrow">{{ $action->getLabel() }}</p>
                <div class="mt-3 flex items-center gap-3">
                    @php
                        $displayName = $action->concernsNewcomer()
                            ? trim($profile->firstName.' '.mb_strtoupper($profile->lastName))
                            : $this->beneficiaryUser?->name;
                    @endphp
                    <flux:avatar size="sm" icon="user" :name="$displayName ?: '—'" />
                    <div class="min-w-0 text-sm leading-tight">
                        <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $displayName ?: __('À renseigner') }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ $action->concernsNewcomer() ? __('Nouvel arrivant') : $this->beneficiaryUser?->job_title }}</p>
                    </div>
                </div>
            </div>
        </x-slot:aside>
    @endif

    @include('livewire.requests.profile.'.$step)
</x-wizard>
