<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/** Section « Matériels » de la fiche profil utilisateur. */
enum EquipmentType: string implements HasLabel
{
    use EnumHelpers;

    case Desktop = 'desktop';
    case Laptop = 'laptop';
    case Tablet = 'tablet';
    case NetworkPrinter = 'network_printer';
    case LocalPrinter = 'local_printer';
    case Scanner = 'scanner';

    public function getLabel(): string
    {
        return match ($this) {
            self::Desktop => __('Poste de travail (PC)'),
            self::Laptop => __('Ordinateur portable'),
            self::Tablet => __('Tablette'),
            self::NetworkPrinter => __('Imprimante réseau'),
            self::LocalPrinter => __('Imprimante locale'),
            self::Scanner => __('Scanneur'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Desktop => 'computer-desktop',
            self::Laptop => 'command-line',
            self::Tablet => 'device-tablet',
            self::NetworkPrinter, self::LocalPrinter => 'printer',
            self::Scanner => 'document-magnifying-glass',
        };
    }
}
