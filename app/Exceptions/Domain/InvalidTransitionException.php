<?php

namespace App\Exceptions\Domain;

use App\Enums\RequestStatus;

final class InvalidTransitionException extends DomainException
{
    public static function between(string $reference, RequestStatus $from, RequestStatus $to): self
    {
        return new self(
            __('La demande :reference est « :from » : elle ne peut pas passer à « :to ». Rechargez la page, elle a peut-être déjà été traitée.', [
                'reference' => $reference,
                'from' => $from->getLabel(),
                'to' => $to->getLabel(),
            ]),
            ['reference' => $reference, 'from' => $from->value, 'to' => $to->value],
        );
    }

    public function title(): string
    {
        return __('Opération non autorisée à ce stade');
    }
}
