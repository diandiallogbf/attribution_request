<?php

namespace App\Exceptions\Domain;

/** Levée par un value object lorsqu'une valeur ne respecte pas son format. */
final class InvalidValueException extends DomainException
{
    public static function for(string $valueObject, mixed $value, string $message): self
    {
        return new self($message, [
            'value_object' => $valueObject,
            'value' => is_scalar($value) ? (string) $value : get_debug_type($value),
        ]);
    }

    public function title(): string
    {
        return __('Valeur invalide');
    }
}
