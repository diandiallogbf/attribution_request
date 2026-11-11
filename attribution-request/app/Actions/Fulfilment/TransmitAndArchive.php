<?php

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\Concerns\EnsuresRole;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Models\ServiceRequest;
use App\Models\User;

/** Fiche profil — étapes 05-06 : l'assistante transmet la fiche exécutée et l'archive (GED). */
final readonly class TransmitAndArchive
{
    use EnsuresRole;

    public function __construct(private CloseServiceRequest $close) {}

    public function handle(ServiceRequest $request, User $actor, ?string $comment = null): ServiceRequest
    {
        $this->ensureRole($actor, Role::archiving(), __('transmettre et archiver une fiche'));

        if ($request->status !== RequestStatus::Executed) {
            throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::Closed);
        }

        return $this->close->handle($request, $actor, $comment ?: __('Fiche transmise et archivée.'));
    }
}
