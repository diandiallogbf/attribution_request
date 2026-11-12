<div class="space-y-6">
    <p class="text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Cochez un élément ou les deux. Les étapes suivantes s\'adaptent à votre choix.') }}
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-choice-card type="checkbox" wire:model.live="subject.wantsPhone"
            icon="device-phone-mobile"
            :title="__('Un téléphone')"
            :description="__('Nouvel appareil, remplacement, réparation ou restitution.')" />

        <x-choice-card type="checkbox" wire:model.live="subject.wantsSim"
            icon="signal"
            :title="__('Une ligne SIM')"
            :description="__('Création, transfert, modification, restitution ou suppression d\'un numéro de flotte.')" />
    </div>
    <x-field-error name="subject.wantsPhone" />

    <div class="flex gap-3 rounded-xl bg-zinc-50 p-4 text-[13px] leading-relaxed text-zinc-600 dark:bg-white/[0.03] dark:text-zinc-400">
        <flux:icon.light-bulb variant="mini" class="mt-px size-4 shrink-0 text-amber-500" />
        <p>{{ __('Un nouveau téléphone sans ligne active ? Cochez les deux : la DSI préparera l\'appareil et la puce ensemble.') }}</p>
    </div>
</div>
