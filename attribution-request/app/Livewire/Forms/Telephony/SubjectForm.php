<?php

namespace App\Livewire\Forms\Telephony;

use Closure;
use Livewire\Form;

/** Étape 2 -Objet : téléphone, ligne SIM, ou les deux (section « Attribution » de la fiche). */
class SubjectForm extends Form
{
    public bool $wantsPhone = false;

    public bool $wantsSim = false;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'wantsPhone' => ['boolean', function (string $attribute, mixed $value, Closure $fail): void {
                if (! $this->wantsPhone && ! $this->wantsSim) {
                    $fail(__('Choisissez au moins un élément : un téléphone, une ligne SIM, ou les deux.'));
                }
            }],
            'wantsSim' => ['boolean'],
        ];
    }
}
