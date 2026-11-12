@use('App\Enums\EquipmentType')

<div class="space-y-9">
    <x-form-section :title="__('Matériels')" optional :description="__('Le matériel informatique nécessaire au poste.')">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (EquipmentType::cases() as $type)
                <x-choice-card size="sm" type="checkbox" value="{{ $type->value }}" wire:model.live="profile.equipment"
                    :icon="$type->icon()" :title="$type->getLabel()" />
            @endforeach
        </div>
    </x-form-section>

    <x-form-section :title="__('Téléphone — numéro GSM')" optional
        :description="__('Numéro professionnel du collaborateur, s\'il en a déjà un. Pour un nouveau téléphone, faites une demande de téléphonie.')">
        <div class="max-w-xs">
            <flux:input wire:model.blur="profile.gsmNumber" icon="phone" placeholder="622 12 34 56" inputmode="tel" />
        </div>
    </x-form-section>
</div>
