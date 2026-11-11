<?php

namespace App\ValueObjects;

use App\Exceptions\Domain\InvalidValueException;
use App\ValueObjects\Concerns\IsValueObject;

/** Matricule AGL : exactement 5 chiffres (les zéros de tête sont conservés). */
final readonly class Matricule implements ValueObject
{
    use IsValueObject;

    private function __construct(public string $value) {}

    public static function from(string|int $value): static
    {
        $normalized = preg_replace('/\s+/', '', (string) $value);

        if (! preg_match('/^\d{5}$/', $normalized)) {
            throw InvalidValueException::for(self::class, $value, __('Le matricule doit contenir exactement 5 chiffres (ex. 04217).'));
        }

        return new self($normalized);
    }

    public function toStorage(): string
    {
        return $this->value;
    }
}
