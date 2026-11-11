<?php

namespace App\Filament\Concerns;

use App\Enums\Role;

/**
 * Restreint une ressource du back-office à certains rôles (navigation, pages et actions).
 */
trait RestrictedToRoles
{
    /** @return list<Role> */
    abstract protected static function allowedRoles(): array;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(static::allowedRoles());
    }
}
