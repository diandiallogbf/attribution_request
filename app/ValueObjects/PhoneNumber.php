<?php

namespace App\ValueObjects;

use App\Exceptions\Domain\InvalidValueException;
use App\ValueObjects\Concerns\IsValueObject;

/**
 * Numéro mobile guinéen. Accepte « 622 12 34 56 », « +224 622-12-34-56 », « 00224622123456 »…
 * Stocké au format E.164 (+224622123456).
 */
final readonly class PhoneNumber implements ValueObject
{
    use IsValueObject;

    private const COUNTRY_CODE = '224';

    /** @param  string  $nationalNumber  9 chiffres, commençant par 6 */
    private function __construct(public string $nationalNumber) {}

    public static function from(string|int $value): static
    {
        $digits = preg_replace('/[\s.\-()\x{00A0}\x{202F}]/u', '', (string) $value);

        $digits = match (true) {
            str_starts_with($digits, '+'.self::COUNTRY_CODE) => substr($digits, 4),
            str_starts_with($digits, '00'.self::COUNTRY_CODE) => substr($digits, 5),
            strlen($digits) === 12 && str_starts_with($digits, self::COUNTRY_CODE) => substr($digits, 3),
            default => $digits,
        };

        if (! preg_match('/^6\d{8}$/', $digits)) {
            throw InvalidValueException::for(self::class, $value, __('Le numéro doit être un mobile guinéen de 9 chiffres commençant par 6 (ex. 622 12 34 56).'));
        }

        return new self($digits);
    }

    public function toStorage(): string
    {
        return '+'.self::COUNTRY_CODE.$this->nationalNumber;
    }

    /** 622 12 34 56 */
    public function national(): string
    {
        return preg_replace('/^(\d{3})(\d{2})(\d{2})(\d{2})$/', '$1 $2 $3 $4', $this->nationalNumber);
    }

    /** +224 622 12 34 56 */
    public function international(): string
    {
        return '+'.self::COUNTRY_CODE.' '.$this->national();
    }
}
