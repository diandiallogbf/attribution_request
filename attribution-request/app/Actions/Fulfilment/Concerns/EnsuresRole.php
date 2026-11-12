<?php

namespace App\Actions\Fulfilment\Concerns;

use App\Enums\Role;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\User;

trait EnsuresRole
{
    /**
     * @param  list<Role>  $roles
     */
    private function ensureRole(User $actor, array $roles, string $action): void
    {
        if (! $actor->hasAnyRole($roles)) {
            throw UnauthorizedActionException::notInRole($action);
        }
    }

    /** Équipe IT + respect de la délégation DSI (à appeler sur la demande verrouillée). */
    private function ensureCanFulfil(ServiceRequest $request, User $actor, string $action): void
    {
        $this->ensureRole($actor, Role::fulfilment(), $action);

        if (! $request->canBeFulfilledBy($actor)) {
            throw UnauthorizedActionException::delegatedToSomeoneElse(
                $request->displayReference(),
                $request->loadMissing('handler')->handler?->name ?? __('un autre membre de l\'équipe'),
            );
        }
    }
}
