<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/** Catégorie du bénéficiaire : détermine la grille de dotation applicable. */
enum EmployeeCategory: string implements HasLabel
{
    use EnumHelpers;

    case Director = 'director';
    case Executive = 'executive';
    case Supervisor = 'supervisor';
    case Employee = 'employee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Director => __('Directeur'),
            self::Executive => __('Cadre'),
            self::Supervisor => __('Agent de maîtrise'),
            self::Employee => __('Employé'),
        };
    }
}
