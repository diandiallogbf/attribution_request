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

    /** Validation devenue sans objet (demande annulée, renvoyée ou rejetée par un autre valideur). */
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('En attente'),
            self::Approved => __('Validée'),
            self::Returned => __('Renvoyée pour correction'),
            self::Rejected => __('Rejetée'),
            self::Skipped => __('Sans objet'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Returned => 'orange',
            self::Rejected => 'danger',
            self::Skipped => 'gray',
        };
    }

    /** Un commentaire est obligatoire pour justifier un renvoi ou un rejet. */
    public function requiresComment(): bool
    {
        return in_array($this, [self::Returned, self::Rejected], true);
    }

    /** Décisions qu'un valideur peut rendre. @return list<self> */
    public static function decisions(): array
    {
        return [self::Approved, self::Returned, self::Rejected];
    }
}
