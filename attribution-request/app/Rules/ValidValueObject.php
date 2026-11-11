<?php

namespace App\Rules;

use App\Exceptions\Domain\InvalidValueException;
use App\ValueObjects\ValueObject;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valide un champ de formulaire avec la même règle que le value object :
 * une seule source de vérité pour le format, et le même message partout.
 *
 * Exemple : 'matricule' => ['required', new ValidValueObject(Matricule::class)]
 */
final readonly class ValidValueObject implements ValidationRule
{
    /**
     * @param  class-string<ValueObject>  $class
     */
    public function __construct(private string $class) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail(__('Le champ :attribute est invalide.'));

            return;
        }

        try {
            ($this->class)::from($value);
        } catch (InvalidValueException $exception) {
            $fail($exception->getMessage());
        }
    }
}
