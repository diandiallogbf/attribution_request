<?php

namespace App\ValueObjects;

use App\Exceptions\Domain\InvalidValueException;
use App\Support\Luhn;
use App\ValueObjects\Concerns\IsValueObject;

/** IMEI : 15 chiffres dont une clé de Luhn. */
final readonly class Imei implements ValueObject
{
    use IsValueObject;

    private function __construct(public string $value) {}

    public static function from(string|int $value): static
    {
        $digits = preg_replace('/[\s\-\/]/', '', (string) $value);

        if (! preg_match('/^\d{15}$/', $digits) || ! Luhn::isValid($digits)) {
            throw InvalidValueException::for(self::class, $value, __('L\'IMEI doit comporter 15 chiffres valides. Composez *#06# sur le téléphone pour l\'afficher.'));
        }

        return new self($digits);
    }

    public function toStorage(): string
    {
        return $this->value;
    }

    /** 35-209900-176148-1 (TAC-série-clé) */
    public function formatted(): string
    {
        return substr($this->value, 0, 2).'-'.substr($this->value, 2, 6).'-'.substr($this->value, 8, 6).'-'.substr($this->value, 14, 1);
    }
}
