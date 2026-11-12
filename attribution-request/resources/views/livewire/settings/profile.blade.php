<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Mon profil')" :subheading="__('Informations issues de l\'annuaire AGL, reprises automatiquement sur vos demandes.')">
        <div class="surface overflow-hidden">
            <div class="flex items-center gap-4 border-b border-zinc-200 px-5 py-5 dark:border-white/10">
                <flux:avatar size="lg" :name="$user->name" :initials="$user->initials()" />
                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</p>
                    <p class="truncate text-sm text-zinc-500">{{ $user->email }}</p>
                </div>
            </div>
            <dl class="grid gap-x-8 gap-y-4 px-5 py-5 text-sm sm:grid-cols-2">
                @foreach ([
                    __('Matricule') => $user->matricule,
                    __('Fonction') => $user->job_title,
                    __('Catégorie') => $user->category?->getLabel(),
                    __('Service') => $user->department?->name,
                    __('Direction') => $user->department?->direction?->name,
                    __('Site') => $user->site?->name,
                    __('Responsable (N+1)') => $user->manager?->name,
                ] as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                        <dd class="numeric mt-0.5 truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <flux:callout icon="information-circle" color="zinc" class="mt-6">
            <flux:callout.text>{{ __('Une information est erronée (service, fonction, responsable) ? Contactez la DRH : elle sera corrigée dans l\'annuaire.') }}</flux:callout.text>
        </flux:callout>
    </x-settings.layout>
</section>
