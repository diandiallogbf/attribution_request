<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/** Options de ligne cochées sur la fiche (AI / AN / IR). */
enum LineOption: string implements HasLabel
{
    use EnumHelpers;

    case InternationalCalls = 'international_calls';
    case NationalCalls = 'national_calls';
    case Roaming = 'roaming';

    public function getLabel(): string
    {
        return match ($this) {
            self::InternationalCalls => __('Appels internationaux'),
            self::NationalCalls => __('Appels nationaux'),
            self::Roaming => __('Itinérance (roaming)'),
        };
    }

    /** Code historique de la fiche papier. */
    public function code(): string
    {
        return match ($this) {
            self::InternationalCalls => 'AI',
            self::NationalCalls => 'AN',
            self::Roaming => 'IR',
        };
    }
}
