<?php

namespace App\Casts;

use App\ValueObjects\ValueObject;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast générique : la base contient la forme de stockage, le modèle expose le value object.
 * En écriture, une chaîne brute est validée par le value object (exception si invalide).
 *
 * @template TValueObject of ValueObject
 *
 * @implements CastsAttributes<TValueObject|null, TValueObject|string|int|null>
 */
final class ValueObjectCast implements CastsAttributes
{
    /**
     * @param  class-string<TValueObject>  $class
     */
    public function __construct(private readonly string $class) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?ValueObject
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($this->class)::from($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $this->class) {
            return $value->toStorage();
        }

        return ($this->class)::from($value)->toStorage();
    }
}
