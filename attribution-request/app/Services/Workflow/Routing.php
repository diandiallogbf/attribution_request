<?php

namespace App\Services\Workflow;

use App\Enums\RequestStatus;
use App\Models\RequestApproval;

/** Résultat d'un aiguillage : statut cible + validations nouvellement attendues (à notifier). */
final readonly class Routing
{
    /**
     * @param  list<RequestApproval>  $newApprovals
     */
    public function __construct(
        public RequestStatus $status,
        public array $newApprovals = [],
    ) {}
}
