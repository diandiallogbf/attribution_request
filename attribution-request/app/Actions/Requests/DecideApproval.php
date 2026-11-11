<?php

namespace App\Actions\Requests;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\Notifications\RequestDecided;
use App\Services\Workflow\Routing;
use App\Services\Workflow\WorkflowRouter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Étapes de validation (manager, métier, DSI) : valider, renvoyer pour correction ou rejeter.
 * La décision est horodatée avec l'IP et le navigateur (signature électronique simple).
 * En validation métier, plusieurs directeurs signent en parallèle : la DSI n'est saisie qu'après le dernier.
 */
final readonly class DecideApproval
{
    public function __construct(private WorkflowRouter $router) {}

    public function handle(ServiceRequest $request, User $actor, ApprovalDecision $decision, ?string $comment = null): ServiceRequest
    {
        if (! in_array($decision, ApprovalDecision::decisions(), true)) {
            throw new \InvalidArgumentException("Décision non valable : {$decision->value}");
        }

        if ($decision->requiresComment() && blank($comment)) {
            throw ValidationException::withMessages([
                'comment' => __('Expliquez au demandeur pourquoi la demande est :decision.', ['decision' => mb_strtolower($decision->getLabel())]),
            ]);
        }

        /** @var Routing|null $routing */
        $routing = null;
        $statusChanged = false;

        $request = DB::transaction(function () use ($request, $actor, $decision, $comment, &$routing, &$statusChanged): ServiceRequest {
            /** @var ServiceRequest $request */
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $request->load(['approvals.direction', 'profileDetail']);

            $step = ApprovalStep::forStatus($request->status);

            if ($step === null) {
                throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::Approved);
            }

            /** @var RequestApproval|null $approval */
            $approval = $request->approvals
                ->filter(fn (RequestApproval $a) => $a->step === $step && $a->decision === ApprovalDecision::Pending)
                ->first(fn (RequestApproval $a) => $a->isAssignedTo($actor));

            if ($approval === null) {
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

            $changes = ['step' => $step->value, 'direction' => $approval->direction?->code];

            if ($decision !== ApprovalDecision::Approved) {
                $this->router->skipPending($request, __('Sans objet : la demande a été :decision.', ['decision' => mb_strtolower($decision->getLabel())]), $approval);
                $target = $decision === ApprovalDecision::Returned ? RequestStatus::Returned : RequestStatus::Rejected;
                $request->transitionTo($target, $actor, $decision === ApprovalDecision::Returned ? RequestEventType::Returned : RequestEventType::Rejected, $comment, $changes);
                $statusChanged = true;

                return $request;
            }

            $routing = match ($step) {
                ApprovalStep::Manager => $this->router->afterManager($request, $actor),
                ApprovalStep::Business => $this->router->afterBusiness($request),
                ApprovalStep::Dsi => new Routing(RequestStatus::Approved),
            };

            if ($routing === null) {
                // Validation métier partielle : d'autres directeurs doivent encore signer.
                $request->recordEvent(RequestEventType::Approved, $actor, $comment, $changes);

                return $request;
            }

            $request->transitionTo($routing->status, $actor, RequestEventType::Approved, $comment, $changes);
            $statusChanged = true;

            return $request;
        });

        foreach ($routing?->newApprovals ?? [] as $approval) {
            ApprovalRequested::dispatchTo($approval);
        }

        if ($statusChanged) {
            $request->requester->notify(new RequestDecided($request, $decision, $actor, $comment));
        }

        return $request;
    }
}
