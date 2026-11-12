<?php

namespace App\ValueObjects;

use App\Exceptions\Domain\InvalidValueException;
use App\ValueObjects\Concerns\IsValueObject;
use Carbon\CarbonInterface;

/**
 * N° de fiche au format AGL « AA MM XXX » : 2611541 = novembre 2026, 541e fiche du mois.
 *
 * Le compteur est mensuel et commun aux deux fiches (téléphonie et profil) : un numéro
 * désigne une seule fiche archivée. Au-delà de 999 fiches dans le mois, le numéro
 * s'allonge (26111000) plutôt que de bloquer une soumission.
 */
final readonly class RequestReference implements ValueObject
{
    use IsValueObject;

    private function __construct(
        public int $year,
        public int $month,
        public int $sequence,
    ) {}

    public static function make(int $year, int $month, int $sequence): self
    {
        if ($month < 1 || $month > 12 || $sequence < 1 || $year < 2000 || $year > 2099) {
            throw InvalidValueException::for(self::class, "{$year}-{$month}-{$sequence}", __('Numéro de fiche impossible à construire.'));
        }

        return new self($year, $month, $sequence);
    }

    public static function forPeriod(CarbonInterface $date, int $sequence): self
    {
        return self::make($date->year, $date->month, $sequence);
    }

    public static function from(string|int $value): static
    {
        $normalized = preg_replace('/\s+/', '', (string) $value);

        if (! preg_match('/^(\d{2})(0[1-9]|1[0-2])(\d{3,})$/', $normalized, $matches) || (int) $matches[3] === 0) {
            throw InvalidValueException::for(self::class, $value, __('N° de fiche invalide (format attendu AAMMXXX, ex. 2611541).'));
        }

        return new self(2000 + (int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    /** Clé du compteur mensuel : « 2611 ». */
    public function period(): string
    {
        return self::periodOf($this->year, $this->month);
    }

    public static function periodOf(int $year, int $month): string
    {
        return sprintf('%02d%02d', $year % 100, $month);
    }

    public function toStorage(): string
    {
        return $this->period().sprintf('%03d', $this->sequence);
    }

    /** Lecture à voix haute / impression aérée : « 26 11 541 ». */
    public function spaced(): string
    {
        return sprintf('%02d %02d %03d', $this->year % 100, $this->month, $this->sequence);
    }
}
