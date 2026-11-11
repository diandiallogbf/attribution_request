<div class="space-y-9">
    <x-form-section :title="__('Applications business')"
        :description="__('Laissez vide si non applicable. Les applications marquées d\'une direction exigent la validation de son directeur avant mise en œuvre.')">
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($this->applications as $application)
                <x-choice-card size="sm" type="checkbox" value="{{ $application->id }}" wire:model.live="profile.applicationIds"
                    icon="cube"
                    :title="$application->name"
                    :description="$application->description"
                    :badge="$application->needsBusinessApproval() ? __('Validation :code', ['code' => $application->ownerDirection->code]) : null"
                    badge-tone="amber" />
            @endforeach
        </div>
        <x-field-error name="profile.applicationIds" />
    </x-form-section>

    @php $circuit = $this->circuit; @endphp
    @if ($circuit['directions']->isNotEmpty())
        <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-[13px] leading-relaxed text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/5 dark:text-amber-200">
            <flux:icon.shield-check variant="mini" class="mt-px size-4 shrink-0" />
            <p>
                {{ trans_choice('Validation métier requise par :count direction : |Validation métier requise par :count directions : ', $circuit['directions']->count()) }}
                <strong>{{ $circuit['directions']->map(fn ($d) => $d->code.($d->director ? ' ('.$d->director->name.')' : '')) ->join(', ') }}</strong>.
                {{ __('Tous doivent valider avant que la DSI ne traite la demande.') }}
            </p>
        </div>
    @endif

    <x-form-section :title="__('Autre application')" optional>
        <flux:input wire:model.blur="profile.otherApplication" :placeholder="__('Nom de l\'application et profil souhaité')" />
    </x-form-section>

    <x-form-section :title="__('Commentaires')" optional>
        <flux:textarea wire:model.blur="profile.comment" rows="2" :placeholder="__('Rôle ou périmètre souhaité dans les applications, contraintes…')" />
    </x-form-section>
</div>
