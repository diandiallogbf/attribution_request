<div class="space-y-9">
    <x-form-section :title="__('Accès de base')" :description="__('Cochez ce dont le collaborateur a besoin.')">
        <div class="grid gap-3 sm:grid-cols-3">
            <x-choice-card size="sm" type="checkbox" wire:model.live="profile.needsEmail" icon="envelope" :title="__('E-mail')" :description="__('Boîte Microsoft 365')" />
            <x-choice-card size="sm" type="checkbox" wire:model.live="profile.needsAs400" icon="server-stack" :title="__('AS400')" :description="__('Session sur le système central')" />
            <x-choice-card size="sm" type="checkbox" wire:model.live="profile.needsInternet" icon="globe-alt" :title="__('Internet')" :description="__('Navigation web')" />
        </div>

        @if ($profile->needsEmail)
            <div class="mt-4">
                <flux:textarea wire:model.blur="profile.distributionLists" rows="2"
                    :label="__('Listes de diffusion')"
                    :description="__('Groupes auxquels l\'utilisateur doit appartenir, séparés par des virgules.')"
                    placeholder="exploitation@…, quai3@…" />
            </div>
        @endif
    </x-form-section>

    <x-form-section :title="__('Profil de référence')" optional
        :description="__('Un collègue qui a déjà exactement les accès souhaités : l\'IT dupliquera son profil.')">
        <flux:input wire:model.blur="profile.referenceProfile" icon="user-group" :placeholder="__('Ex. : même profil que Mariama SOW')" />
    </x-form-section>
</div>
