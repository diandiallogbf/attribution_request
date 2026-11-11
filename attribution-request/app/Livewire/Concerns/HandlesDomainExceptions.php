<?php

namespace App\Livewire\Concerns;

use App\Exceptions\Domain\DomainException;
use Flux\Flux;
use Throwable;

/**
 * Une erreur métier dans un composant Livewire devient un toast explicite
 * (et un avertissement dans les logs) au lieu d'une page d'erreur.
 */
trait HandlesDomainExceptions
{
    /**
     * Hook Livewire : les paramètres sont injectés par leur nom ($e, $stopPropagation), ne pas les renommer.
     */
    public function exception(Throwable $e, callable $stopPropagation): void
    {
        if (! $e instanceof DomainException) {
            return;
        }

        report($e);

        Flux::toast(text: $e->getMessage(), heading: $e->title(), variant: 'danger', duration: 8000);

        $stopPropagation();
    }
}
