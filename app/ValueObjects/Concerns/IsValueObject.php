<?php

namespace App\ValueObjects\Concerns;

use App\Casts\ValueObjectCast;
use App\Exceptions\Domain\InvalidValueException;
use App\ValueObjects\ValueObject;

/**
 * @mixin ValueObject
 */
trait IsValueObject
{
    /**
     * Permet d'écrire `'matricule' => Matricule::class` dans les casts d'un modèle.
     *
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): ValueObjectCast
    {
        return new ValueObjectCast(static::class);
    }

    /** Variante tolérante : null si la valeur est vide ou invalide. */
    public static function tryFrom(string|int|null $value): ?static
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return static::from($value);
        } catch (InvalidValueException) {
            return null;
        }
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof static && $other->toStorage() === $this->toStorage();
    }

    public function jsonSerialize(): string|int
    {
        return $this->toStorage();
    }

    public function __toString(): string
    {
        return (string) $this->toStorage();
    }
}
