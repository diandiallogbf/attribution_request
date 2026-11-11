<?php

namespace App\Actions\Requests;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\Notifications\RequestDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Étapes 02 (manager) et 03 (DSI) : valider, renvoyer pour correction ou rejeter.
 * La décision est horodatée avec l'IP et le navigateur (signature électronique simple).
 */
final class DecideApproval
{
    public function handle(ServiceRequest $request, User $actor, ApprovalDecision $decision, ?string $comment = null): ServiceRequest
    {
        if ($decision === ApprovalDecision::Pending) {
            throw new \InvalidArgumentException('Une décision ne peut pas être « en attente ».');
        }

        if ($decision->requiresComment() && blank($comment)) {
            throw ValidationException::withMessages([
                'comment' => __('Expliquez au demandeur pourquoi la demande est :decision.', ['decision' => mb_strtolower($decision->getLabel())]),
            ]);
        }

        $next = null;

        $request = DB::transaction(function () use ($request, $actor, $decision, $comment, &$next): ServiceRequest {
            /** @var ServiceRequest $request */
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $request->status->isAwaitingApproval()) {
                throw InvalidTransitionException::between($request->displayReference(), $request->status, $this->targetStatus($request->status, $decision));
            }

            /** @var RequestApproval|null $approval */
            $approval = $request->approvals()
                ->pending()
                ->where('step', $this->stepFor($request->status))
                ->latest('id')
                ->first();

            if ($approval === null || ! $approval->isAssignedTo($actor)) {
                throw UnauthorizedActionException::notTheApprover($request->displayReference());
            }

            if ($request->beneficiary_id === $actor->id) {
                throw UnauthorizedActionException::selfApproval($request->displayReference());
            }

            $approval->update([
                'decision' => $decision,
                'decided_by_id' => $actor->id,
                'decided_at' => now(),
                'comment' => $comment,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $target = $this->targetStatus($request->status, $decision);

            $request->transitionTo($target, $actor, match ($decision) {
                ApprovalDecision::Approved => RequestEventType::Approved,
                ApprovalDecision::Returned => RequestEventType::Returned,
                ApprovalDecision::Rejected => RequestEventType::Rejected,
            }, $comment, ['step' => $approval->step->value]);

            if ($target === RequestStatus::PendingDsi) {
                $next = $request->approvals()->create(['step' => ApprovalStep::Dsi, 'assigned_role' => Role::Dsi->value]);
            }

            return $request;
        });

        if ($next !== null) {
            ApprovalRequested::dispatchTo($next);
        }

        $request->requester->notify(new RequestDecided($request, $decision, $actor, $comment));

        return $request;
    }

    private function stepFor(RequestStatus $status): ApprovalStep
    {
        return $status === RequestStatus::PendingManager ? ApprovalStep::Manager : ApprovalStep::Dsi;
    }

    private function targetStatus(RequestStatus $current, ApprovalDecision $decision): RequestStatus
    {
        return match ($decision) {
            ApprovalDecision::Approved => $current === RequestStatus::PendingManager ? RequestStatus::PendingDsi : RequestStatus::Approved,
            ApprovalDecision::Returned => RequestStatus::Returned,
            ApprovalDecision::Rejected => RequestStatus::Rejected,
            ApprovalDecision::Pending => $current,
        };
    }
}
