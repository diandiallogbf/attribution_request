<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Brouillon'),
            self::Sent => __('Envoyée'),
            self::PartiallyReceived => __('Reçue partiellement'),
            self::Received => __('Reçue'),
            self::Cancelled => __('Annulée'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::Sent => 'info',
            self::PartiallyReceived => 'warning',
            self::Received => 'success',
        };
    }
}
