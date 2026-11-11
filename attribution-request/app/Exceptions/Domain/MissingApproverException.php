<?php

namespace App\Exceptions\Domain;

final class MissingApproverException extends DomainException
{
    public static function noManager(string $beneficiary): self
    {
        return new self(
            __('Aucun manager n\'est défini pour :name. Contactez l\'administrateur pour compléter l\'organigramme avant de soumettre.', ['name' => $beneficiary]),
            ['beneficiary' => $beneficiary],
        );
    }

    public function title(): string
    {
        return __('Valideur introuvable');
    }
}
