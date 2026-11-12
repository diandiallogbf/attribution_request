@use('App\Enums\ProfileAction')

<div class="space-y-6">
    @if ($requestId)
        <flux:callout icon="lock-closed" color="zinc">
            <flux:callout.text>{{ __('Le type de demande est fixé sur ce brouillon. Pour un autre besoin, créez une nouvelle fiche.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-3">
        @foreach (ProfileAction::cases() as $case)
            @php $disabled = $requestId !== null || ($case->concernsNewcomer() && ! $this->canCreateForNewcomers); @endphp
            <x-choice-card name="profile-action" value="{{ $case->value }}" wire:model.live="profile.action"
                :disabled="$disabled"
                :icon="$case->icon()" :title="$case->getLabel()"
                :description="$case->concernsNewcomer() && ! $this->canCreateForNewcomers
                    ? __('Réservé aux managers, chefs de service et directeurs.')
                    : $case->description()" />
        @endforeach
    </div>
    <x-field-error name="profile.action" />
</div>
