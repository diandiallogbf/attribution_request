<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum ProfileType: string implements HasLabel
{
    use EnumHelpers;

    case Permanent = 'permanent';
    case Internship = 'internship';

    public function getLabel(): string
    {
        return match ($this) {
            self::Permanent => __('Permanent'),
            self::Internship => __('Stage'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Permanent => __('Collaborateur en CDI / CDD.'),
            self::Internship => __('Accès fermés automatiquement à la date de fin.'),
        };
    }
}
