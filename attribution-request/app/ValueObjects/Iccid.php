<?php

namespace App\ValueObjects;

use App\Exceptions\Domain\InvalidValueException;
use App\Support\Luhn;
use App\ValueObjects\Concerns\IsValueObject;

/** ICCID : numéro imprimé sur la carte SIM, 19 ou 20 chiffres commençant par 89, avec clé de Luhn. */
final readonly class Iccid implements ValueObject
{
    use IsValueObject;

    private function __construct(public string $value) {}

    public static function from(string|int $value): static
    {
        $digits = preg_replace('/[\s\-]/', '', (string) $value);

        if (! preg_match('/^89\d{17,18}$/', $digits) || ! Luhn::isValid($digits)) {
            throw InvalidValueException::for(self::class, $value, __('L\'ICCID (numéro imprimé sur la carte SIM) doit comporter 19 ou 20 chiffres et commencer par 89.'));
        }

        return new self($digits);
    }

    public function toStorage(): string
    {
        return $this->value;
    }
}
