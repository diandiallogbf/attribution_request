<?php

namespace App\Livewire\Forms\Telephony;

use Illuminate\Validation\Rule;
use Livewire\Form;

/** Étape 1 — Pour qui est la demande ? */
class BeneficiaryForm extends Form
{
    public ?int $beneficiaryId = null;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'beneficiaryId' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'beneficiaryId.required' => __('Choisissez le collaborateur concerné par la demande.'),
            'beneficiaryId.exists' => __('Ce collaborateur est introuvable ou son compte est désactivé.'),
        ];
    }
}
