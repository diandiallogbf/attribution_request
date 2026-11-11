<?php

namespace App\Actions\Requests;

use App\Enums\ApprovalDecision;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Le demandeur peut annuler tant que la DSI n'a pas commencé à traiter la demande. */
final class CancelServiceRequest
{
    public function handle(ServiceRequest $request, User $actor, ?string $reason = null): ServiceRequest
    {
        if ($request->requester_id !== $actor->id) {
            throw UnauthorizedActionException::notAllowedToRequestFor($request->beneficiary->name);
        }

        return DB::transaction(function () use ($request, $actor, $reason): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            // Les validations encore attendues deviennent sans objet.
            $request->approvals()->pending()->update(['decision' => ApprovalDecision::Rejected, 'comment' => __('Demande annulée par le demandeur.')]);

            $request->transitionTo(RequestStatus::Cancelled, $actor, RequestEventType::Cancelled, $reason);

            return $request;
        });
    }
}
