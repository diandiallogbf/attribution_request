<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum RequestType: string implements HasLabel
{
    use EnumHelpers;

    /** Fiche d'attribution de téléphone (AGL-GIN-DSI-F-0015) : téléphone et/ou ligne SIM. */
    case Telephony = 'telephony';

    /** Fiche profil utilisateur (AGL-GIN-DSI-F-0008) — phase ultérieure. */
    case UserProfile = 'user_profile';

    public function getLabel(): string
    {
        return match ($this) {
            self::Telephony => __('Téléphonie'),
            self::UserProfile => __('Profil utilisateur'),
        };
    }

    /** Préfixe de la référence lisible : TEL-2026-00042. */
    public function referencePrefix(): string
    {
        return match ($this) {
            self::Telephony => 'TEL',
            self::UserProfile => 'PRF',
        };
    }

    public function formCode(): string
    {
        return match ($this) {
            self::Telephony => 'AGL-GIN-DSI-F-0015 Rév. 5.1',
            self::UserProfile => 'AGL-GIN-DSI-F-0008 Rév. 3',
        };
    }

    public static function fromReferencePrefix(string $prefix): self
    {
        foreach (self::cases() as $case) {
            if ($case->referencePrefix() === $prefix) {
                return $case;
            }
        }

        throw new \ValueError("Préfixe de référence inconnu : {$prefix}");
    }
}
