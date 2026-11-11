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

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => __('Administrateur'),
            self::Dsi => __('DSI — approbation'),
            self::ItManager => __('Responsable IT'),
            self::ItAgent => __('Technicien IT'),
            self::Assistant => __('Assistante (chrono / GED)'),
            self::Director => __('Directeur'),
        };
    }

    /** Rôles autorisés à entrer dans le back-office Filament. */
    public static function backOffice(): array
    {
        return [self::Admin, self::Dsi, self::ItManager, self::ItAgent, self::Assistant];
    }
}
