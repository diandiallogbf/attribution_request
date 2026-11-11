<?php

namespace App\Rules;

use App\ValueObjects\ValueObject;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Unicité calculée sur la forme normalisée du value object : « 622 12 34 56 » et « +224622123456 »
 * désignent le même numéro.
 */
final readonly class UniqueValueObject implements ValidationRule
{
    /**
     * @param  class-string<ValueObject>  $valueObject
     * @param  class-string<Model>  $model
     */
    public function __construct(
        private string $valueObject,
        private string $model,
        private string $column,
        private ?Model $ignore = null,
        private ?string $message = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = ($this->valueObject)::tryFrom(is_scalar($value) ? (string) $value : null);

        if ($normalized === null) {
            return; // le format est contrôlé par ValidValueObject
        }

        $exists = ($this->model)::query()
            ->where($this->column, $normalized->toStorage())
            ->when($this->ignore?->exists, fn ($query) => $query->whereKeyNot($this->ignore->getKey()))
            ->exists();

        if ($exists) {
            $fail($this->message ?? __('Cette valeur est déjà enregistrée.'));
        }
    }
}
