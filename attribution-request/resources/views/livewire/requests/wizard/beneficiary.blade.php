@php
    $me = auth()->user();
    $person = $this->beneficiaryUser;
    $locked = $requestId !== null;
@endphp

<div class="space-y-8">
    @if (! $locked && $this->teamMembers->isNotEmpty())
        <x-form-section :title="__('Bénéficiaire')">
            <div x-data="{ team: @js($beneficiary->beneficiaryId !== null && $beneficiary->beneficiaryId !== $me->id) }" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-choice-card name="beneficiary-mode" value="self" x-bind:checked="! team"
                        x-on:change="team = false; $wire.set('beneficiary.beneficiaryId', {{ $me->id }})"
                        icon="user" :title="__('Pour moi')" :description="__('Je suis le bénéficiaire du téléphone ou de la ligne.')" />
                    <x-choice-card name="beneficiary-mode" value="team" x-bind:checked="team"
                        x-on:change="team = true; $wire.set('beneficiary.beneficiaryId', null)"
                        icon="user-group" :title="__('Pour un membre de mon équipe')"
                        :description="trans_choice(':count collaborateur rattaché à vous|:count collaborateurs rattachés à vous', $this->teamMembers->count())" />
                </div>

                <div x-show="team" x-cloak x-transition.opacity>
                    <flux:select wire:model.live="beneficiary.beneficiaryId" :label="__('Collaborateur')" :placeholder="__('Choisir un collaborateur…')">
                        @foreach ($this->teamMembers as $member)
                            <flux:select.option :value="$member->id">{{ $member->name }} -{{ $member->job_title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <x-field-error name="beneficiary.beneficiaryId" />
            </div>
        </x-form-section>
    @endif

    @if ($person)
        <x-form-section :title="$locked || $this->teamMembers->isEmpty() ? __('Vos informations') : __('Informations du bénéficiaire')"
            :description="__('Issues de l\'annuaire AGL et reprises automatiquement sur la fiche. Une erreur ? Signalez-la à la DRH.')">
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10">
                <div class="flex items-center gap-4 border-b border-zinc-200 bg-zinc-50/70 px-5 py-4 dark:border-white/10 dark:bg-white/[0.02]">
                    <flux:avatar :name="$person->name" :initials="$person->initials()" />
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $person->name }}
                            @if ($person->nickname)<span class="font-normal text-zinc-500"> « {{ $person->nickname }} »</span>@endif
                        </p>
                        <p class="truncate text-sm text-zinc-500">{{ $person->job_title }}</p>
                    </div>
                    @if ($person->category)
                        <span class="ms-auto hidden rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-700/15 sm:inline dark:bg-brand-400/10 dark:text-brand-200">{{ $person->category->getLabel() }}</span>
                    @endif
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-4 px-5 py-5 text-sm sm:grid-cols-2">
                    @foreach ([
                        __('Matricule') => $person->matricule ?? '—',
                        __('Service') => $person->department?->name ?? '—',
                        __('Direction') => $person->department?->direction?->name ?? '—',
                        __('Site') => $person->site?->name ?? '—',
                        __('Dernier téléphone attribué') => $person->lastPhoneAssignedAt()?->translatedFormat('d F Y') ?? __('Aucun'),
                        __('Validera la demande') => $this->approver
                            ? ($this->approver->id === $me->id ? __('Vous (validation d\'office)') : $this->approver->name)
                            : __('Non défini'),
                    ] as $label => $value)
                        <div class="min-w-0">
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                            <dd class="mt-0.5 truncate font-medium text-zinc-900 numeric dark:text-zinc-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @unless ($this->approver)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-4">
                    <flux:callout.heading>{{ __('Aucun manager n\'est défini pour :name', ['name' => $person->first_name]) }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Vous pouvez préparer la demande, mais elle ne pourra être envoyée qu\'une fois l\'organigramme complété par l\'administrateur.') }}</flux:callout.text>
                </flux:callout>
            @endunless

            @if ($locked)
                <p class="mt-3 text-xs text-zinc-500">{{ __('Le bénéficiaire ne peut plus être changé sur ce brouillon. Pour une autre personne, créez une nouvelle demande.') }}</p>
            @endif
        </x-form-section>
    @endif
</div>
