@use('App\Enums\EmployeeCategory')
@use('App\Enums\ProfileAction')
@use('App\Enums\ProfileType')

@php
    $action = $profile->actionEnum();
    $me = auth()->user();
    $locked = $requestId !== null && ! $action?->concernsNewcomer();
@endphp

<div class="space-y-9">
    @if ($action?->concernsNewcomer())
        <x-form-section :title="__('Identité')" :description="__('Telle qu\'elle figurera sur ses comptes (e-mail, AS400…).')">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.blur="profile.firstName" :label="__('Prénom')" autocomplete="off" />
                <flux:input wire:model.blur="profile.lastName" :label="__('Nom')" autocomplete="off" />
                <flux:input wire:model.blur="profile.matricule" :label="__('Matricule')" :badge="__('Si déjà attribué')" placeholder="04217" maxlength="5" inputmode="numeric" />
                <flux:input wire:model.blur="profile.jobTitle" :label="__('Fonction')" :placeholder="__('Ex. : Agent de pointage')" />
            </div>
        </x-form-section>

        <x-form-section :title="__('Rattachement')">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="profile.departmentId" :label="__('Service')" :placeholder="__('Choisir un service…')">
                    @foreach ($this->directions as $direction)
                        @foreach ($direction->departments as $department)
                            <flux:select.option :value="$department->id">{{ $direction->code }} -{{ $department->name }}</flux:select.option>
                        @endforeach
                    @endforeach
                </flux:select>
                <flux:select wire:model="profile.siteId" :label="__('Site (location)')" :placeholder="__('Choisir un site…')">
                    @foreach ($this->sites as $site)
                        <flux:select.option :value="$site->id">{{ $site->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="profile.category" :label="__('Catégorie')" :placeholder="__('Choisir…')">
                    @foreach (EmployeeCategory::cases() as $category)
                        <flux:select.option :value="$category->value">{{ $category->getLabel() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="profile.managerId" :label="__('Responsable (N+1)')" :description="__('Il validera la demande (étape 2).')">
                    @foreach ($this->managers as $user)
                        <flux:select.option :value="$user->id">{{ $user->name }}{{ $user->id === $me->id ? ' -'.__('moi') : '' }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </x-form-section>

        <x-form-section :title="__('Type de profil')">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (ProfileType::cases() as $type)
                    <x-choice-card size="sm" name="profile-type" value="{{ $type->value }}" wire:model.live="profile.profileType"
                        :icon="$type === ProfileType::Permanent ? 'briefcase' : 'academic-cap'"
                        :title="$type->getLabel()" :description="$type->description()" />
                @endforeach
            </div>
            <x-field-error name="profile.profileType" />

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model.blur="profile.effectiveAt" :label="__('Date de prise de poste')" :badge="__('Facultatif')" />
                @if ($profile->profileType === ProfileType::Internship->value)
                    <flux:input type="date" wire:model.blur="profile.internshipEndsAt" :label="__('Date de fin de stage')" :description="__('Les accès seront fermés à cette date.')" />
                @endif
            </div>
        </x-form-section>
    @else
        <x-form-section :title="__('Collaborateur')">
            @if ($locked)
                <p class="mb-3 text-xs text-zinc-500">{{ __('Le collaborateur ne peut plus être changé sur ce brouillon.') }}</p>
            @endif
            <flux:select wire:model.live="profile.beneficiaryId" :disabled="$locked" :placeholder="__('Choisir un collaborateur…')">
                <flux:select.option :value="$me->id">{{ $me->name }} -{{ __('moi') }}</flux:select.option>
                @foreach ($this->teamMembers as $member)
                    <flux:select.option :value="$member->id">{{ $member->name }} -{{ $member->job_title }}</flux:select.option>
                @endforeach
            </flux:select>
            <x-field-error name="profile.beneficiaryId" />

            @if ($person = $this->beneficiaryUser)
                <dl class="mt-4 grid gap-x-8 gap-y-3 rounded-xl border border-zinc-200 px-5 py-4 text-sm sm:grid-cols-3 dark:border-white/10">
                    @foreach ([
                        __('Matricule') => $person->matricule,
                        __('Service') => $person->department?->name,
                        __('Site') => $person->site?->name,
                    ] as $label => $value)
                        <div><dt class="text-xs text-zinc-500">{{ $label }}</dt><dd class="numeric mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                </dl>
            @endif
        </x-form-section>

        <x-form-section :title="$action === ProfileAction::Deletion ? __('Date de départ') : __('Date d\'effet')"
            :optional="$action !== ProfileAction::Deletion"
            :description="$action === ProfileAction::Deletion ? __('Tous les comptes et accès seront fermés à cette date.') : null">
            <div class="max-w-xs">
                <flux:input type="date" wire:model.blur="profile.effectiveAt" />
            </div>
        </x-form-section>

        @if ($action === ProfileAction::Deletion)
            <x-form-section :title="__('Commentaire')" optional>
                <flux:textarea wire:model.blur="profile.comment" rows="2" :placeholder="__('Ex. : fin de contrat, transfert vers une autre entité…')" />
            </x-form-section>
        @endif
    @endif
</div>
