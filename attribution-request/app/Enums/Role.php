<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/**
 * Rôles applicatifs (spatie/laravel-permission). Le rôle « manager » n'existe pas :
 * c'est la relation users.manager_id qui désigne le N+1 d'un collaborateur.
 */
enum Role: string implements HasLabel
{
    use EnumHelpers;

    case Admin = 'admin';
    case Dsi = 'dsi';
    case ItManager = 'it_manager';
    case ItAgent = 'it_agent';
    case Assistant = 'assistant';
    case Director = 'director';
    case Finance = 'finance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => __('Administrateur'),
            self::Dsi => __('DSI — approbation'),
            self::ItManager => __('Responsable IT'),
            self::ItAgent => __('Technicien IT'),
            self::Assistant => __('Assistante (chrono / GED)'),
            self::Director => __('Directeur'),
            self::Finance => __('Finance / comptabilité'),
        };
    }

    /** Rôles autorisés à entrer dans le back-office Filament. @return list<self> */
    public static function backOffice(): array
    {
        return [self::Admin, self::Dsi, self::ItManager, self::ItAgent, self::Assistant, self::Finance];
    }

    /** Consultent les dépenses (commandes, recharges) et les exportent. @return list<self> */
    public static function financeReaders(): array
    {
        return [self::Admin, self::Dsi, self::Finance];
    }

    /** Saisissent les écritures comptables : facture, imputation, paiement. @return list<self> */
    public static function accounting(): array
    {
        return [self::Admin, self::Finance];
    }

    /** Commande, réception, attribution, exécution des profils (étapes 04-05). @return list<self> */
    public static function fulfilment(): array
    {
        return [self::Admin, self::Dsi, self::ItManager, self::ItAgent];
    }

    /** Peuvent confier la commande / l'exécution d'une demande à un membre de l'équipe. @return list<self> */
    public static function delegators(): array
    {
        return [self::Admin, self::Dsi];
    }

    /** Équipe de la DSI à qui la commande / l'exécution peut être déléguée. @return list<self> */
    public static function delegates(): array
    {
        return [self::ItManager, self::ItAgent];
    }

    /** Transmission et archivage de la fiche (étape 06 « Clôture → GED »). @return list<self> */
    public static function archiving(): array
    {
        return [self::Admin, self::Dsi, self::ItManager, self::Assistant];
    }
}
