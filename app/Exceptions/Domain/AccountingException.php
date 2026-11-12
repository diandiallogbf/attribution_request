<?php

namespace App\Exceptions\Domain;

/** Saisie comptable incohérente (facture, règlement). */
final class AccountingException extends DomainException
{
    public static function cancelledOrder(string $reference): self
    {
        return new self(__('La commande :reference est annulée : elle ne peut pas être comptabilisée.', ['reference' => $reference]), ['reference' => $reference]);
    }

    public static function paymentWithoutInvoice(): self
    {
        return new self(__('Saisissez la facture (n° et montant) avant d\'enregistrer le règlement.'));
    }

    public static function paymentBeforeInvoice(): self
    {
        return new self(__('La date de règlement ne peut pas précéder la date de facture.'));
    }

    public static function futureDate(string $what): self
    {
        return new self(__('La date de :what ne peut pas être dans le futur.', ['what' => $what]), ['field' => $what]);
    }

    public function title(): string
    {
        return __('Saisie comptable refusée');
    }
}
