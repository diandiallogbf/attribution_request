<?php

namespace App\Exceptions\Domain;

use RuntimeException;
use Throwable;

/**
 * Erreur métier attendue : son message est rédigé pour l'utilisateur final (en français)
 * et s'affiche tel quel (toast Livewire, flash HTTP ou JSON 422).
 * Le contexte est ajouté automatiquement aux logs par Laravel via context().
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, protected array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Titre court affiché au-dessus du message. */
    public function title(): string
    {
        return __('Action impossible');
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
