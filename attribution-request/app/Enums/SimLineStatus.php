<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SimLineStatus: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case InStock = 'in_stock';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function getLabel(): string
    {
        return match ($this) {
            self::InStock => __('En stock'),
            self::Active => __('Active'),
            self::Suspended => __('Suspendue'),
            self::Terminated => __('Résiliée'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InStock => 'gray',
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Terminated => 'danger',
        };
    }
}
