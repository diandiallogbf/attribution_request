<?php

namespace App\Enums\Concerns;

/**
 * @mixin \BackedEnum
 */
trait EnumHelpers
{
    /** @return list<string|int> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Options prêtes pour un <select> ou des boutons radio : [valeur => libellé].
     *
     * @return array<string|int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
