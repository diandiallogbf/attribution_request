<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum RequestType: string implements HasLabel
{
    use EnumHelpers;

    /** Fiche d'attribution de téléphone (AGL-GIN-DSI-F-0015) : téléphone et/ou ligne SIM. */
    case Telephony = 'telephony';

    /** Fiche profil utilisateur (AGL-GIN-DSI-F-0008) : accès, applications métier, matériel. */
    case UserProfile = 'user_profile';

    public function getLabel(): string
    {
        return match ($this) {
            self::Telephony => __('Téléphonie'),
            self::UserProfile => __('Profil utilisateur'),
        };
    }

    public function formTitle(): string
    {
        return match ($this) {
            self::Telephony => __('Fiche d\'attribution de téléphone'),
            self::UserProfile => __('Fiche profil utilisateur'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Telephony => 'device-phone-mobile',
            self::UserProfile => 'identification',
        };
    }

    /** Dossier de classement de la GED (le N° de fiche, lui, est commun aux deux fiches). */
    public function archiveFolder(): string
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

    /** L'engagement « procédure de gestion de la téléphonie » n'existe que sur la fiche F-0015. */
    public function requiresPolicyAcceptance(): bool
    {
        return $this === self::Telephony;
    }

    /**
     * Les 6 étapes de la procédure (notes DSI), pour la frise de suivi.
     *
     * @return array<int, string>
     */
    public function stages(): array
    {
        return match ($this) {
            self::Telephony => [
                1 => __('Initiation'),
                2 => __('Validation manager'),
                3 => __('Approbation DSI'),
                4 => __('Commande'),
                5 => __('Attribution'),
                6 => __('Clôture'),
            ],
            self::UserProfile => [
                1 => __('Initiation'),
                2 => __('Validation manager'),
                3 => __('Validation métier'),
                4 => __('Validation DSI'),
                5 => __('Exécution & transmission'),
                6 => __('Clôture'),
            ],
        };
    }
}
