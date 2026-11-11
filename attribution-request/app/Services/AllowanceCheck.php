<?php

namespace App\Services;

use App\Models\AllowanceRule;
use Carbon\CarbonInterface;

/** Résultat du contrôle de la grille de dotation, affiché au demandeur, au manager et à la DSI. */
final readonly class AllowanceCheck
{
    /**
     * @param  list<string>  $violations
     * @param  list<string>  $warnings
     */
    public function __construct(
        public ?AllowanceRule $rule,
        public array $violations = [],
        public array $warnings = [],
        public ?CarbonInterface $renewalEligibleAt = null,
    ) {}

    public function isCompliant(): bool
    {
        return $this->violations === [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }
}
