<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    /** Liste globale (back-office Filament). */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(Role::backOffice());
    }

    /** Demandeur, bénéficiaire, N+1 du bénéficiaire, valideur concerné, ou back-office. */
    public function view(User $user, ServiceRequest $request): bool
    {
        return $request->requester_id === $user->id
            || $request->beneficiary_id === $user->id
            || $request->beneficiary?->manager_id === $user->id
            || $user->hasAnyRole(Role::backOffice())
            || $request->approvals->contains(fn (RequestApproval $approval) => $approval->isAssignedTo($user));
    }

    public function update(User $user, ServiceRequest $request): bool
    {
        return $request->requester_id === $user->id && $request->status->isEditableByRequester();
    }

    public function cancel(User $user, ServiceRequest $request): bool
    {
        return $request->requester_id === $user->id
            && in_array($request->status, [RequestStatus::Draft, RequestStatus::PendingManager, RequestStatus::Returned], true);
    }

    /** Une validation attend cet utilisateur à l'étape courante. */
    public function decide(User $user, ServiceRequest $request): bool
    {
        if (! $request->status->isAwaitingApproval() || $request->beneficiary_id === $user->id) {
            return false;
        }

        $approval = $request->pendingApproval();

        return $approval !== null && $approval->isAssignedTo($user);
    }
}
