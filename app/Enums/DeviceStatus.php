<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeviceStatus: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case InStock = 'in_stock';
    case Assigned = 'assigned';
    case InRepair = 'in_repair';
    case Retired = 'retired';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::InStock => __('En stock'),
            self::Assigned => __('Attribué'),
            self::InRepair => __('En réparation'),
            self::Retired => __('Réformé'),
            self::Lost => __('Perdu / volé'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::Assigned => 'primary',
            self::InRepair => 'warning',
            self::Retired => 'gray',
            self::Lost => 'danger',
        };
    }
}
