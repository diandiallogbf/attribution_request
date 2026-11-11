<?php

namespace App\Services;

use App\Exceptions\Domain\MissingApproverException;
use App\Models\Department;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Collection;

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
     * Valideur de l'étape 2 pour une demande : N+1 du bénéficiaire, ou futur N+1 désigné
     * sur la fiche lorsqu'il s'agit d'un nouveau collaborateur (création de profil).
     *
     * @throws MissingApproverException
     */
    public function managerForRequest(ServiceRequest $request): User
    {
        if ($request->beneficiary_id !== null) {
            return $this->managerFor($request->beneficiary);
        }

        $manager = $request->profileDetail?->manager;

        if ($manager?->is_active) {
            return $manager;
        }

        throw MissingApproverException::noManager($request->beneficiaryName());
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

    /**
     * Collaborateurs pour lesquels l'utilisateur peut faire une demande : ses N-1 directs
     * et les membres des services qu'il dirige — jamais sa propre hiérarchie.
     *
     * @return Collection<int, User>
     */
    public function teamOf(User $user): Collection
    {
        return User::query()
            ->active()
            ->whereKeyNot($user->id)
            ->when($user->manager_id, fn ($query, int $managerId) => $query->whereKeyNot($managerId))
            ->where(fn ($query) => $query
                ->where('manager_id', $user->id)
                ->orWhereIn('department_id', Department::query()->where('head_id', $user->id)->select('id')))
            ->orderBy('last_name')
            ->get();
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
