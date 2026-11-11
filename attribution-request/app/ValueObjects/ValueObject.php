<?php

namespace App\ValueObjects;

use App\Exceptions\Domain\InvalidValueException;
use Illuminate\Contracts\Database\Eloquent\Castable;
use JsonSerializable;
use Stringable;

/**
 * Contrat commun des value objects : construction validée (from), forme de stockage
 * (toStorage) et égalité par valeur. Castable : utilisable directement dans $casts.
 */
interface ValueObject extends Castable, JsonSerializable, Stringable
{
    /**
     * @throws InvalidValueException
     */
    public static function from(string|int $value): static;

    public function toStorage(): string|int;

    public function equals(self $other): bool;
}
