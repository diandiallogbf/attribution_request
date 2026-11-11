<?php

namespace App\ValueObjects;

use App\Enums\RequestType;
use App\Exceptions\Domain\InvalidValueException;
use App\ValueObjects\Concerns\IsValueObject;

/** Référence lisible d'une demande : TEL-2026-00042 (type, année, séquence annuelle). */
final readonly class RequestReference implements ValueObject
{
    use IsValueObject;

    private function __construct(
        public RequestType $type,
        public int $year,
        public int $sequence,
    ) {}

    public static function make(RequestType $type, int $year, int $sequence): self
    {
        return new self($type, $year, $sequence);
    }

    public static function from(string|int $value): static
    {
        if (! preg_match('/^([A-Z]{3})-(\d{4})-(\d{5,})$/', (string) $value, $matches)) {
            throw InvalidValueException::for(self::class, $value, __('Référence de demande invalide (format attendu : TEL-2026-00042).'));
        }

        try {
            $type = RequestType::fromReferencePrefix($matches[1]);
        } catch (\ValueError) {
            throw InvalidValueException::for(self::class, $value, __('Type de demande inconnu dans la référence.'));
        }

        return new self($type, (int) $matches[2], (int) $matches[3]);
    }

    public function toStorage(): string
    {
        return sprintf('%s-%04d-%05d', $this->type->referencePrefix(), $this->year, $this->sequence);
    }
}
