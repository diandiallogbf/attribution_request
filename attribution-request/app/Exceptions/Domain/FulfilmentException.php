<?php

namespace App\Exceptions\Domain;

/** Erreurs des étapes de commande, réception, attribution et exécution. */
final class FulfilmentException extends DomainException
{
    public static function nothingToOrder(string $reference): self
    {
        return new self(__('La demande :reference ne nécessite pas de commande de ce type.', ['reference' => $reference]), ['reference' => $reference]);
    }

    public static function outOfStock(string $model): self
    {
        return new self(__('Aucun « :model » disponible en stock : passez une commande.', ['model' => $model]), ['model' => $model]);
    }

    public static function receivedCountMismatch(int $expected, int $given, string $what): self
    {
        return new self(
            __('Réception incomplète : :expected :what attendu(s), :given saisi(s).', ['expected' => $expected, 'given' => $given, 'what' => $what]),
            ['expected' => $expected, 'given' => $given],
        );
    }

    public static function lineNotInInventory(string $number): self
    {
        return new self(
            __('La ligne :number n\'est pas enregistrée dans le parc : ajoutez-la d\'abord (Parc → Lignes SIM).', ['number' => $number]),
            ['msisdn' => $number],
        );
    }

    public static function missing(string $what): self
    {
        return new self(__('Information manquante : :what.', ['what' => $what]), ['missing' => $what]);
    }

    public static function notBeneficiary(string $reference): self
    {
        return new self(__('Seul le bénéficiaire peut confirmer la réception de :reference.', ['reference' => $reference]), ['reference' => $reference]);
    }

    public function title(): string
    {
        return __('Traitement impossible');
    }
}
