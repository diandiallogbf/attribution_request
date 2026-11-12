<?php

namespace App\Support;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use App\Models\User;

/** Compteurs et droits affichés dans la navigation (badges « Mes demandes » / « À valider »). */
final class Navigation
{
    /**
     * @return array{open_requests: int, pending_approvals: int, is_approver: bool, back_office: bool}
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return ['open_requests' => 0, 'pending_approvals' => 0, 'is_approver' => false, 'back_office' => false];
        }

        $pending = RequestApproval::query()->awaiting($user)->count();

        return [
            'open_requests' => ServiceRequest::query()->involving($user)->open()->where('status', '!=', RequestStatus::Draft)->count(),
            'pending_approvals' => $pending,
            'is_approver' => $pending > 0 || $user->isManager() || $user->hasAnyRole([Role::Dsi, Role::Director]),
            'back_office' => $user->hasAnyRole(Role::backOffice()),
        ];
    }
}
