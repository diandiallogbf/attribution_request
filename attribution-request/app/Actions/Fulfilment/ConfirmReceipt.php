<?php

namespace App\Actions\Fulfilment;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\FulfilmentException;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Validation demandeur / téléphone » : le bénéficiaire confirme la réception et renouvelle
 * son engagement (horodaté = signature électronique). La demande est alors clôturée et archivée.
 */
final readonly class ConfirmReceipt
{
    public function __construct(private CloseServiceRequest $close) {}

    public function handle(ServiceRequest $request, User $actor, bool $policyAccepted): ServiceRequest
    {
        if (! $policyAccepted) {
            throw ValidationException::withMessages([
                'policyAccepted' => __('Confirmez votre engagement à respecter la procédure de gestion de la téléphonie.'),
            ]);
        }

        $request = DB::transaction(function () use ($request, $actor): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== RequestStatus::PendingReceipt) {
                throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::Closed);
            }

            if ($request->beneficiary_id !== $actor->id) {
                throw FulfilmentException::notBeneficiary($request->displayReference());
            }

            $request->assignments()->whereNull('received_at')->update([
                'received_at' => now(),
                'policy_accepted_at' => now(),
                'updated_at' => now(),
            ]);

            $request->recordEvent(RequestEventType::ReceiptConfirmed, $actor, __('Engagement à respecter la procédure de gestion de la téléphonie accepté.'));

            return $request;
        });

        return $this->close->handle($request, $actor);
    }
}
