<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum ApprovalStep: string implements HasLabel
{
    use EnumHelpers;

    case Manager = 'manager';
    case Dsi = 'dsi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manager => __('Validation manager'),
            self::Dsi => __('Approbation DSI'),
        };
    }

    /** Statut de la demande pendant lequel cette validation est attendue. */
    public function pendingStatus(): RequestStatus
    {
        return match ($this) {
            self::Manager => RequestStatus::PendingManager,
            self::Dsi => RequestStatus::PendingDsi,
        };
    }
}
