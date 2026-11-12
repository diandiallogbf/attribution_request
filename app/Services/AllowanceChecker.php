<?php

namespace App\Services;

use App\Enums\PhoneType;
use App\Models\AllowanceRule;
use App\Models\User;
use App\ValueObjects\MoneyGnf;
use Illuminate\Support\Collection;

/**
 * Contrôle automatique de la grille de dotation (étape 03 « Vérifier : grille, modèle, type »).
 * Il n'est pas bloquant pour le demandeur : un écart demande une justification et est signalé à la DSI,
 * qui tranche (dérogation tracée).
 */
final class AllowanceChecker
{
    /** @return Collection<int, PhoneType> */
    public function allowedPhoneTypes(User $beneficiary): Collection
    {
        return $this->ruleFor($beneficiary)?->allowed_phone_types ?? collect(PhoneType::cases());
    }

    public function maxMonthlyRecharge(User $beneficiary): ?MoneyGnf
    {
        return $this->ruleFor($beneficiary)?->max_monthly_recharge_gnf;
    }

    public function check(User $beneficiary, ?PhoneType $phoneType = null, ?MoneyGnf $monthlyRecharge = null, bool $isNewDevice = false): AllowanceCheck
    {
        $rule = $this->ruleFor($beneficiary);

        if ($rule === null) {
            return new AllowanceCheck(null, warnings: [
                __('Aucune grille de dotation n\'est définie pour la catégorie de :name : la DSI vérifiera manuellement.', ['name' => $beneficiary->first_name]),
            ]);
        }

        $violations = [];
        $warnings = [];
        $renewalEligibleAt = null;

        if ($phoneType !== null && $phoneType !== PhoneType::Other && ! $rule->allowsPhoneType($phoneType)) {
            $violations[] = __('Le type « :type » n\'est pas prévu pour la catégorie :category.', [
                'type' => $phoneType->getLabel(),
                'category' => $rule->employee_category->getLabel(),
            ]);
        }

        if ($monthlyRecharge !== null && ! $rule->allowsRecharge($monthlyRecharge)) {
            $violations[] = __('La recharge demandée (:amount) dépasse le plafond de :max pour la catégorie :category.', [
                'amount' => $monthlyRecharge->format(),
                'max' => $rule->max_monthly_recharge_gnf->format(),
                'category' => $rule->employee_category->getLabel(),
            ]);
        }

        if ($isNewDevice && ($lastPhoneAt = $beneficiary->lastPhoneAssignedAt())) {
            $renewalEligibleAt = $lastPhoneAt->copy()->addMonths($rule->renewal_months);

            if ($renewalEligibleAt->isFuture()) {
                $warnings[] = __('Dernier téléphone attribué le :date : renouvellement prévu à partir du :eligible (:months mois).', [
                    'date' => $lastPhoneAt->translatedFormat('d F Y'),
                    'eligible' => $renewalEligibleAt->translatedFormat('d F Y'),
                    'months' => $rule->renewal_months,
                ]);
            }
        }

        return new AllowanceCheck($rule, $violations, $warnings, $renewalEligibleAt);
    }

    /** @var array<string, AllowanceRule|null> */
    private array $rules = [];

    private function ruleFor(User $beneficiary): ?AllowanceRule
    {
        if ($beneficiary->category === null) {
            return null;
        }

        $key = $beneficiary->category->value;

        if (! array_key_exists($key, $this->rules)) {
            $this->rules[$key] = AllowanceRule::forCategory($beneficiary->category);
        }

        return $this->rules[$key];
    }
}
