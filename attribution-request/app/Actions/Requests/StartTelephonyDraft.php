<?php

namespace App\Actions\Requests;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ApproverResolver;
use Illuminate\Support\Facades\DB;

/** Étape 01 — Initiation : ouvre un brouillon dès que le bénéficiaire est choisi. */
final readonly class StartTelephonyDraft
{
    public function __construct(private ApproverResolver $approvers) {}

    public function handle(User $requester, User $beneficiary): ServiceRequest
    {
        if (! $this->approvers->canRequestFor($requester, $beneficiary)) {
            throw UnauthorizedActionException::notAllowedToRequestFor($beneficiary->name);
        }

        return DB::transaction(function () use ($requester, $beneficiary): ServiceRequest {
            $request = ServiceRequest::create([
                'type' => RequestType::Telephony,
                'status' => RequestStatus::Draft,
                'requester_id' => $requester->id,
                'beneficiary_id' => $beneficiary->id,
                'site_id' => $beneficiary->site_id,
            ]);

            $request->recordEvent(RequestEventType::Created, $requester);

            return $request;
        });
    }
}
