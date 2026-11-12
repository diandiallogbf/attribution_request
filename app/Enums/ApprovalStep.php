<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum ApprovalStep: string implements HasLabel
{
    use EnumHelpers;

    case Manager = 'manager';

    /** Validation métier : un directeur par direction propriétaire d'une application demandée (fiche Profil). */
    case Business = 'business';

    case Dsi = 'dsi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manager => __('Validation manager'),
            self::Business => __('Validation métier'),
            self::Dsi => __('Approbation DSI'),
        };
    }

    /** Statut de la demande pendant lequel cette validation est attendue. */
    public function pendingStatus(): RequestStatus
    {
        return match ($this) {
            self::Manager => RequestStatus::PendingManager,
            self::Business => RequestStatus::PendingBusiness,
            self::Dsi => RequestStatus::PendingDsi,
        };
    }

    public static function forStatus(RequestStatus $status): ?self
    {
        foreach (self::cases() as $step) {
            if ($step->pendingStatus() === $status) {
                return $step;
            }
        }

        return null;
    }
}
