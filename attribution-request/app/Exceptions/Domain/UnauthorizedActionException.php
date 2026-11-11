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

    public static function cannotRequestForNewcomer(): self
    {
        return new self(__('La création de profil pour un nouveau collaborateur est réservée aux managers, chefs de service et directeurs.'));
    }

    public static function notInRole(string $action): self
    {
        return new self(__('Votre rôle ne permet pas de :action.', ['action' => $action]), ['action' => $action]);
    }

    public function title(): string
    {
        return __('Accès refusé');
    }
}
