<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/** Motif de la demande de téléphone (section « Information téléphone » de la fiche). */
enum PhoneReason: string implements HasLabel
{
    use EnumHelpers;

    case Acquisition = 'acquisition';
    case Replacement = 'replacement';
    case Repair = 'repair';
    case Return = 'return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Acquisition => __('Acquisition'),
            self::Replacement => __('Remplacement'),
            self::Repair => __('Réparation'),
            self::Return => __('Restitution'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Acquisition => __('Premier téléphone professionnel ou nouveau besoin.'),
            self::Replacement => __('Le téléphone actuel est perdu, cassé ou en fin de vie.'),
            self::Repair => __('Le téléphone actuel doit être réparé.'),
            self::Return => __('Rendre le téléphone actuel à la DSI.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Acquisition => 'sparkles',
            self::Replacement => 'arrow-path',
            self::Repair => 'wrench-screwdriver',
            self::Return => 'arrow-uturn-left',
        };
    }

    /** L'ancien téléphone doit être identifié (modèle / n° de série). */
    public function requiresPreviousDevice(): bool
    {
        return $this !== self::Acquisition;
    }

    /** Un nouvel appareil doit être fourni (choix du type, contrôle de la grille, commande éventuelle). */
    public function requiresNewDevice(): bool
    {
        return in_array($this, [self::Acquisition, self::Replacement], true);
    }
}
