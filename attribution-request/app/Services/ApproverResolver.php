<?php

namespace App\Services;

use App\Exceptions\Domain\MissingApproverException;
use App\Models\User;

/**
 * Détermine qui doit valider. Aujourd'hui : le N+1 (users.manager_id), à défaut le chef de service.
 * Demain : le même calcul alimenté par la synchronisation Microsoft Entra ID (attribut manager).
 */
final class ApproverResolver
{
    /**
     * @throws MissingApproverException
     */
    public function managerFor(User $beneficiary): User
    {
        $manager = $beneficiary->manager;

        if ($manager?->is_active && $manager->id !== $beneficiary->id) {
            return $manager;
        }

        $head = $beneficiary->department?->head;

        if ($head?->is_active && $head->id !== $beneficiary->id) {
            return $head;
        }

        throw MissingApproverException::noManager($beneficiary->name);
    }

    /**
     * Si le demandeur est lui-même le valideur du bénéficiaire, l'étape « validation manager »
     * est réputée faite au moment de la soumission (règle validée avec la DSI).
     */
    public function requesterIsApprover(User $requester, User $beneficiary): bool
    {
        if ($requester->id === $beneficiary->id) {
            return false;
        }

        try {
            return $this->managerFor($beneficiary)->id === $requester->id;
        } catch (MissingApproverException) {
            return false;
        }
    }

    /** Le demandeur peut-il faire une demande pour ce bénéficiaire ? (lui-même ou son équipe, jamais sa hiérarchie) */
    public function canRequestFor(User $requester, User $beneficiary): bool
    {
        if ($requester->id === $beneficiary->id) {
            return true;
        }

        if ($requester->manager_id === $beneficiary->id) {
            return false;
        }

        return $requester->isManagerOf($beneficiary)
            || $beneficiary->department?->head_id === $requester->id;
    }
}
