<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasLabel
{
    use EnumHelpers;

    /** Bon de commande fournisseur pour des appareils. */
    case DevicePurchase = 'device_purchase';

    /** Demande de puce / ligne auprès de l'opérateur (Orange Guinée). */
    case OperatorRequest = 'operator_request';

    public function getLabel(): string
    {
        return match ($this) {
            self::DevicePurchase => __('Bon de commande appareils'),
            self::OperatorRequest => __('Demande opérateur (SIM)'),
        };
    }
}
