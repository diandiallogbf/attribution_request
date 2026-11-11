<?php

namespace App\Exceptions\Domain;

final class DuplicateOpenRequestException extends DomainException
{
    public static function for(string $beneficiary, string $existingReference): self
    {
        return new self(
            __(':name a déjà une demande en cours (:reference). Suivez-la ou annulez-la avant d\'en créer une nouvelle.', [
                'name' => $beneficiary,
                'reference' => $existingReference,
            ]),
            ['beneficiary' => $beneficiary, 'existing_reference' => $existingReference],
        );
    }

    public function title(): string
    {
        return __('Demande déjà en cours');
    }
}
