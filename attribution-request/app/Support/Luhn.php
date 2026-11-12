<?php

namespace App\Support;

/** Algorithme de Luhn (clé de contrôle des IMEI et ICCID). */
final class Luhn
{
    public static function isValid(string $digits): bool
    {
        if ($digits === '' || ! ctype_digit($digits)) {
            return false;
        }

        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    /** Ajoute la clé de contrôle à une suite de chiffres (utile pour générer des IMEI/ICCID de test). */
    public static function complete(string $partial): string
    {
        for ($check = 0; $check <= 9; $check++) {
            if (self::isValid($partial.$check)) {
                return $partial.$check;
            }
        }

        throw new \LogicException('Impossible de calculer la clé de Luhn.');
    }
}
