<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/** Création / Modification / Suppression (fiche profil utilisateur). */
enum ProfileAction: string implements HasLabel
{
    use EnumHelpers;

    case Creation = 'creation';
    case Modification = 'modification';
    case Deletion = 'deletion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Creation => __('Création'),
            self::Modification => __('Modification'),
            self::Deletion => __('Suppression'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Creation => __('Nouveau collaborateur ou stagiaire : ouvrir ses comptes et ses accès.'),
            self::Modification => __('Ajouter ou retirer des accès à un collaborateur existant.'),
            self::Deletion => __('Départ ou fin de stage : fermer les comptes et les accès.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Creation => 'user-plus',
            self::Modification => 'adjustments-horizontal',
            self::Deletion => 'user-minus',
        };
    }

    /** Le bénéficiaire n'existe pas encore dans l'annuaire : son identité est saisie sur la fiche. */
    public function concernsNewcomer(): bool
    {
        return $this === self::Creation;
    }

    /** Accès, applications et matériel sont à préciser (inutile pour une suppression : tout est fermé). */
    public function requiresAccessDetails(): bool
    {
        return $this !== self::Deletion;
    }
}
