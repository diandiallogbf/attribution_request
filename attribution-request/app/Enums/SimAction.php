<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/** Action demandée sur la ligne (section « Information numéro (SIM) » de la fiche). */
enum SimAction: string implements HasLabel
{
    use EnumHelpers;

    case Creation = 'creation';
    case Transfer = 'transfer';
    case Modification = 'modification';
    case Return = 'return';
    case Deletion = 'deletion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Creation => __('Création'),
            self::Transfer => __('Transfert'),
            self::Modification => __('Modification'),
            self::Return => __('Restitution'),
            self::Deletion => __('Suppression'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Creation => __('Nouvelle ligne de flotte.'),
            self::Transfer => __('Transférer une ligne existante vers ce bénéficiaire.'),
            self::Modification => __('Changer le forfait, les options ou la recharge.'),
            self::Return => __('Rendre la puce à la DSI.'),
            self::Deletion => __('Résilier la ligne auprès de l\'opérateur.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Creation => 'plus-circle',
            self::Transfer => 'arrows-right-left',
            self::Modification => 'adjustments-horizontal',
            self::Return => 'arrow-uturn-left',
            self::Deletion => 'trash',
        };
    }

    /** Toute action autre qu'une création porte sur une ligne existante. */
    public function requiresExistingLine(): bool
    {
        return $this !== self::Creation;
    }

    /** Le forfait, les options et le montant de recharge sont à (re)définir. */
    public function requiresPlan(): bool
    {
        return in_array($this, [self::Creation, self::Transfer, self::Modification], true);
    }
}
