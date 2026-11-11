<?php

namespace App\Livewire\Forms\Telephony;

use App\Enums\LineOption;
use App\Enums\PlanType;
use App\Enums\SimAction;
use App\Models\RechargeAmount;
use App\Rules\ValidValueObject;
use App\ValueObjects\PhoneNumber;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Étape 4 -Ligne SIM (sections « Information numéro (SIM) » et « Montant à recharger »). */
class SimForm extends Form
{
    public ?string $action = null;

    /** Id d'une ligne attribuée au bénéficiaire, ou « other » pour saisir un numéro. */
    public ?string $existingLineChoice = null;

    public string $existingMsisdn = '';

    public ?string $planType = null;

    /** @var list<string> */
    public array $options = [];

    public ?string $monthlyRecharge = null;

    public string $entity = '';

    public function actionEnum(): ?SimAction
    {
        return SimAction::tryFrom((string) $this->action);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $action = $this->actionEnum();
        $needsLine = (bool) $action?->requiresExistingLine();
        $needsPlan = (bool) $action?->requiresPlan();

        return [
            'action' => ['required', Rule::enum(SimAction::class)],
            'existingLineChoice' => [Rule::requiredIf($needsLine), 'nullable', 'string'],
            'existingMsisdn' => [Rule::requiredIf($needsLine && $this->existingLineChoice === 'other'), 'nullable', 'string', new ValidValueObject(PhoneNumber::class)],
            'planType' => [Rule::requiredIf($needsPlan), 'nullable', Rule::enum(PlanType::class)],
            'options' => ['array'],
            'options.*' => [Rule::enum(LineOption::class)],
            'monthlyRecharge' => [
                Rule::requiredIf($needsPlan),
                'nullable',
                Rule::in(RechargeAmount::query()->where('is_active', true)->toBase()->pluck('amount_gnf')->map(fn ($amount) => (string) $amount)->all()),
            ],
            'entity' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'action.required' => __('Choisissez l\'action à réaliser sur la ligne.'),
            'existingLineChoice.required' => __('Indiquez la ligne concernée.'),
            'existingMsisdn.required' => __('Saisissez le numéro de la ligne concernée.'),
            'planType.required' => __('Choisissez le type de forfait.'),
            'monthlyRecharge.required' => __('Choisissez le montant de recharge mensuelle.'),
            'monthlyRecharge.in' => __('Ce montant ne fait pas partie de la grille de recharge.'),
        ];
    }
}
