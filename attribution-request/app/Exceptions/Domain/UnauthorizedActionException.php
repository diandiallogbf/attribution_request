<?php

namespace App\Exceptions\Domain;

final class UnauthorizedActionException extends DomainException
{
    public static function notTheApprover(string $reference): self
    {
        return new self(
            __('Vous n\'êtes pas le valideur attendu pour la demande :reference.', ['reference' => $reference]),
            ['reference' => $reference],
        );
    }

    public static function selfApproval(string $reference): self
    {
        return new self(
            __('Vous ne pouvez pas valider une demande dont vous êtes le bénéficiaire (:reference).', ['reference' => $reference]),
            ['reference' => $reference],
        );
    }

    public static function notAllowedToRequestFor(string $beneficiary): self
    {
        return new self(
            __('Vous ne pouvez faire une demande que pour vous-même ou pour un membre de votre équipe (:name).', ['name' => $beneficiary]),
            ['beneficiary' => $beneficiary],
        );
    }

    public function title(): string
    {
        return __('Accès refusé');
    }
}
