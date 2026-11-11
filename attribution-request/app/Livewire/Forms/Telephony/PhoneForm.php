<?php

namespace App\Livewire\Forms\Telephony;

use App\Enums\PhoneReason;
use App\Enums\PhoneType;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Étape 3 — Téléphone (section « Information téléphone » de la fiche). */
class PhoneForm extends Form
{
    public ?string $reason = null;

    public ?string $phoneType = null;

    public string $otherTypeLabel = '';

    public string $detailedReason = '';

    /** Id d'un appareil du parc attribué au bénéficiaire, ou « other » pour un appareil non répertorié. */
    public ?string $previousDeviceChoice = null;

    public string $previousDeviceLabel = '';

    public string $previousDeviceSerial = '';

    public function reasonEnum(): ?PhoneReason
    {
        return PhoneReason::tryFrom((string) $this->reason);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $reason = $this->reasonEnum();
        $needsNew = (bool) $reason?->requiresNewDevice();
        $needsPrevious = (bool) $reason?->requiresPreviousDevice();

        return [
            'reason' => ['required', Rule::enum(PhoneReason::class)],
            'phoneType' => [Rule::requiredIf($needsNew), 'nullable', Rule::enum(PhoneType::class)],
            'otherTypeLabel' => [Rule::requiredIf($needsNew && $this->phoneType === PhoneType::Other->value), 'nullable', 'string', 'max:100'],
            'detailedReason' => ['required', 'string', 'min:15', 'max:1000'],
            'previousDeviceChoice' => [Rule::requiredIf($needsPrevious), 'nullable', 'string'],
            'previousDeviceLabel' => [Rule::requiredIf($needsPrevious && $this->previousDeviceChoice === 'other'), 'nullable', 'string', 'max:120'],
            'previousDeviceSerial' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'reason.required' => __('Choisissez le motif de la demande.'),
            'phoneType.required' => __('Choisissez le type de téléphone souhaité.'),
            'otherTypeLabel.required' => __('Précisez le modèle ou l\'appareil souhaité.'),
            'detailedReason.required' => __('Le motif détaillé est obligatoire : expliquez votre besoin en quelques mots.'),
            'detailedReason.min' => __('Donnez un peu plus de détails (15 caractères minimum).'),
            'detailedReason.max' => __('Le motif détaillé ne peut pas dépasser 1 000 caractères.'),
            'previousDeviceChoice.required' => __('Indiquez le téléphone concerné.'),
            'previousDeviceLabel.required' => __('Indiquez au moins le modèle du téléphone concerné.'),
        ];
    }
}
