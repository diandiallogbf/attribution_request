@use('App\Enums\EmployeeCategory')
@use('App\Enums\EquipmentType')
@use('App\Enums\ProfileType')
@use('App\ValueObjects\PhoneNumber')

@php
    $action = $profile->actionEnum();
    $circuit = $this->circuit;
    $me = auth()->user();
    $selectedApps = $this->applications->whereIn('id', collect($profile->applicationIds)->map(fn ($id) => (int) $id));
    $department = $this->directions->flatMap->departments->firstWhere('id', $profile->departmentId);

    $identity = $action?->concernsNewcomer()
        ? [
            __('Nom') => trim($profile->firstName.' '.mb_strtoupper($profile->lastName)),
            __('Matricule') => $profile->matricule ?: __('À attribuer'),
            __('Fonction') => $profile->jobTitle,
            __('Service') => $department?->name,
            __('Site') => $this->sites->firstWhere('id', $profile->siteId)?->name,
            __('Catégorie') => EmployeeCategory::tryFrom((string) $profile->category)?->getLabel(),
            __('Type de profil') => ProfileType::tryFrom((string) $profile->profileType)?->getLabel()
                .($profile->internshipEndsAt ? ' -'.__('fin le :date', ['date' => \Illuminate\Support\Facades\Date::parse($profile->internshipEndsAt)->translatedFormat('d F Y')]) : ''),
            __('Prise de poste') => $profile->effectiveAt ? \Illuminate\Support\Facades\Date::parse($profile->effectiveAt)->translatedFormat('d F Y') : null,
        ]
        : [
            __('Nom') => $this->beneficiaryUser?->name,
            __('Matricule') => $this->beneficiaryUser?->matricule,
            __('Fonction') => $this->beneficiaryUser?->job_title,
            __('Service') => $this->beneficiaryUser?->department?->name,
            $action?->value === 'deletion' ? __('Date de départ') : __('Date d\'effet') => $profile->effectiveAt ? \Illuminate\Support\Facades\Date::parse($profile->effectiveAt)->translatedFormat('d F Y') : null,
        ];
@endphp

<div class="space-y-8">
    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-white/10 dark:border-white/10">
        <section class="px-5 py-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                    <flux:icon :icon="$action?->icon() ?? 'user'" variant="micro" class="size-4 text-zinc-400" />
                    {{ $action?->getLabel() }} -{{ __('Information utilisateur') }}
                </h3>
                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="goTo('person')">{{ __('Modifier') }}</flux:button>
            </div>
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                @foreach ($identity as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                        <dd class="numeric mt-0.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($action?->requiresAccessDetails())
            <section class="px-5 py-5">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                        <flux:icon.key variant="micro" class="size-4 text-zinc-400" /> {{ __('Accès et applications') }}
                    </h3>
                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="goTo('access')">{{ __('Modifier') }}</flux:button>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach (array_filter([$profile->needsEmail ? __('E-mail') : null, $profile->needsAs400 ? 'AS400' : null, $profile->needsInternet ? __('Internet') : null]) as $access)
                        <span class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 dark:bg-white/10 dark:text-zinc-200">{{ $access }}</span>
                    @endforeach
                    @foreach ($selectedApps as $app)
                        <span class="rounded-md bg-violet-50 px-2 py-1 text-xs font-medium text-violet-700 ring-1 ring-violet-600/15 dark:bg-violet-400/10 dark:text-violet-300">{{ $app->name }}</span>
                    @endforeach
                    @if ($profile->otherApplication)
                        <span class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 dark:bg-white/10 dark:text-zinc-200">{{ $profile->otherApplication }}</span>
                    @endif
                    @foreach ($profile->equipment as $value)
                        <span class="rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-700/15 dark:bg-brand-400/10 dark:text-brand-200">{{ EquipmentType::tryFrom($value)?->getLabel() }}</span>
                    @endforeach
                    @if ($gsm = PhoneNumber::tryFrom($profile->gsmNumber))
                        <span class="numeric rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-700/15 dark:bg-brand-400/10 dark:text-brand-200">GSM {{ $gsm->international() }}</span>
                    @endif
                </div>
                @if ($profile->distributionLists)
                    <p class="mt-3 text-xs text-zinc-500">{{ __('Listes de diffusion :') }} {{ $profile->distributionLists }}</p>
                @endif
                @if ($profile->referenceProfile)
                    <p class="mt-1 text-xs text-zinc-500">{{ __('Profil de référence :') }} {{ $profile->referenceProfile }}</p>
                @endif
                <x-field-error name="profile.needsEmail" />
            </section>
        @endif
    </div>

    {{-- Circuit de validation --}}
    <x-form-section :title="__('Circuit de validation')">
        @php
            $selfApproved = $circuit['manager']?->id === $me->id;
            $stagesList = [
                ['user-circle', __('Validation manager'), $selfApproved ? __('Validée d\'office (vous êtes le responsable)') : ($circuit['manager']?->name ?? __('Responsable non défini')), $selfApproved],
            ];
            foreach ($circuit['directions'] as $direction) {
                $stagesList[] = ['shield-check', __('Validation métier -:code', ['code' => $direction->code]), $direction->director?->name ?? __('Directeur non défini'), false];
            }
            $stagesList[] = ['building-office-2', __('Validation DSI'), __('Création du profil par l\'IT'), false];
            $stagesList[] = ['archive-box', __('Transmission & GED'), __('Fiche archivée'), false];
        @endphp
        <ol class="space-y-2">
            @foreach ($stagesList as $i => [$icon, $label, $detail, $done])
                <li class="flex items-center gap-3 rounded-xl bg-zinc-50 px-4 py-3 dark:bg-white/[0.03]">
                    <span @class([
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $done,
                        'bg-white text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-white/10' => ! $done,
                    ])>
                        @if ($done) <flux:icon.check variant="micro" class="size-4" /> @else {{ $i + 1 }} @endif
                    </span>
                    <flux:icon :icon="$icon" variant="micro" class="size-4 text-zinc-400" />
                    <span class="min-w-0 flex-1 text-sm">
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $label }}</span>
                        <span class="text-zinc-500"> · {{ $detail }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </x-form-section>

    @if ($action?->requiresAccessDetails())
        <x-form-section :title="__('Commentaire pour les valideurs')" optional>
            <flux:textarea wire:model.blur="profile.comment" rows="2" :placeholder="__('Information utile pour le manager, les directeurs ou la DSI…')" />
        </x-form-section>
    @endif
</div>
