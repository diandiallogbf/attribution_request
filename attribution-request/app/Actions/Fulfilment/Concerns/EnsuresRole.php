<?php

namespace App\Actions\Fulfilment\Concerns;

use App\Enums\Role;
use App\Exceptions\Domain\UnauthorizedActionException;
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
}
