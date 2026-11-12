<?php

namespace App\ValueObjects;

use App\Exceptions\Domain\InvalidValueException;
use App\ValueObjects\Concerns\IsValueObject;

/**
 * Montant en francs guinéens. Le GNF n'a pas de subdivision utilisée : on manipule des entiers.
 * Accepte « 300000 », « 300 000 », « 300.000 GNF ».
 */
final readonly class MoneyGnf implements ValueObject
{
    use IsValueObject;

    private const MAX = 1_000_000_000_000;

    private function __construct(public int $amount) {}

    public static function from(string|int $value): static
    {
        if (is_string($value)) {
            $clean = preg_replace('/[\s.\x{00A0}\x{202F}]|GNF/iu', '', $value);

            if (! ctype_digit($clean)) {
                throw InvalidValueException::for(self::class, $value, __('Le montant doit être un nombre entier de francs guinéens.'));
            }

            $value = (int) $clean;
        }

        if ($value < 0 || $value > self::MAX) {
            throw InvalidValueException::for(self::class, $value, __('Le montant doit être positif.'));
        }

        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function toStorage(): int
    {
        return $this->amount;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    /** Projection (ex. recharge mensuelle × 12). */
    public function times(int $factor): self
    {
        return self::from($this->amount * $factor);
    }

    /** @param  iterable<self|null>  $amounts */
    public static function sum(iterable $amounts): self
    {
        $total = self::zero();

        foreach ($amounts as $amount) {
            if ($amount !== null) {
                $total = $total->add($amount);
            }
        }

        return $total;
    }

    /** « 300 000 GNF » avec espaces insécables (typographie française). */
    public function format(): string
    {
        return number_format($this->amount, 0, ',', "\u{202F}")."\u{00A0}GNF";
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
