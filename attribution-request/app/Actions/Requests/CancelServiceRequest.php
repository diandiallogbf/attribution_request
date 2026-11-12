<?php

namespace App\Actions\Requests;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Workflow\WorkflowRouter;
use Illuminate\Support\Facades\DB;

/** Le demandeur peut annuler tant que la DSI n'a pas commencé à traiter la demande. */
final readonly class CancelServiceRequest
{
    public function __construct(private WorkflowRouter $router) {}

    public function handle(ServiceRequest $request, User $actor, ?string $reason = null): ServiceRequest
    {
        if ($request->requester_id !== $actor->id) {
            throw UnauthorizedActionException::notAllowedToRequestFor($request->beneficiaryName());
        }

        return DB::transaction(function () use ($request, $actor, $reason): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            $this->router->skipPending($request, __('Sans objet : demande annulée par le demandeur.'));

            $request->transitionTo(RequestStatus::Cancelled, $actor, RequestEventType::Cancelled, $reason);

            return $request;
        });
    }
}
