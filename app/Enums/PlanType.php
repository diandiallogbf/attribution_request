<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum PlanType: string implements HasLabel
{
    use EnumHelpers;

    case Postpaid = 'postpaid';
    case Prepaid = 'prepaid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Postpaid => __('Post-payé'),
            self::Prepaid => __('Prépayé'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Postpaid => __('Facturé à AGL chaque mois.'),
            self::Prepaid => __('Rechargé chaque mois du montant alloué.'),
        };
    }
}
