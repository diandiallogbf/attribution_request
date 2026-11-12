<?php

namespace App\Data;

/** Saisie de l'IT lorsqu'il exécute une fiche profil (« Ajoute le profil + image / ticket »). */
final readonly class ProfileExecutionData
{
    /**
     * @param  list<int>  $executedApplicationIds
     */
    public function __construct(
        public array $executedApplicationIds = [],
        public ?string $ticket = null,
        public ?string $notes = null,
        public ?string $proofPath = null,
        public ?string $proofOriginalName = null,
        public bool $createPortalAccount = false,
        public ?string $accountEmail = null,
        public bool $deactivatePortalAccount = false,
    ) {}
}
