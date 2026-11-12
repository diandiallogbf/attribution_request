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

    public static function invalidDelegate(string $name): self
    {
        return new self(
            __(':name ne fait pas partie de l\'équipe IT active : choisissez un responsable IT ou un technicien.', ['name' => $name]),
            ['delegate' => $name],
        );
    }

    public static function notDelegable(string $reference, string $status): self
    {
        return new self(
            __('La demande :reference est « :status » : seule la phase de commande / exécution peut être déléguée.', ['reference' => $reference, 'status' => $status]),
            ['reference' => $reference, 'status' => $status],
        );
    }

    public static function alreadyHandledBy(string $reference, string $name): self
    {
        return new self(__('La demande :reference est déjà confiée à :name.', ['reference' => $reference, 'name' => $name]), ['reference' => $reference]);
    }

    public function title(): string
    {
        return __('Traitement impossible');
    }
}
