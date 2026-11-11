<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApprovalDecision: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('En attente'),
            self::Approved => __('Validée'),
            self::Returned => __('Renvoyée pour correction'),
            self::Rejected => __('Rejetée'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Returned => 'orange',
            self::Rejected => 'danger',
        };
    }

    /** Un commentaire est obligatoire pour justifier un renvoi ou un rejet. */
    public function requiresComment(): bool
    {
        return in_array($this, [self::Returned, self::Rejected], true);
    }
}
