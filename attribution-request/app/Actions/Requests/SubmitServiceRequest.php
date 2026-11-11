<?php

namespace App\Actions\Requests;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Exceptions\Domain\DuplicateOpenRequestException;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\Services\ApproverResolver;
use App\Services\RequestReferenceGenerator;
use App\Services\Workflow\Routing;
use App\Services\Workflow\WorkflowRouter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soumission (ou re-soumission après renvoi), pour les deux fiches.
 * - Si le demandeur est lui-même le N+1 du bénéficiaire, l'étape manager est validée d'office.
 * - Sinon la demande part chez le N+1. La suite (validation métier, DSI) est aiguillée par WorkflowRouter.
 */
final readonly class SubmitServiceRequest
{
    public function __construct(
        private ApproverResolver $approvers,
        private RequestReferenceGenerator $references,
        private WorkflowRouter $router,
    ) {}

    public function handle(ServiceRequest $request, User $actor, bool $policyAccepted, ?string $comment = null): ServiceRequest
    {
        if ($request->type->requiresPolicyAcceptance() && ! $policyAccepted) {
            throw ValidationException::withMessages([
                'policyAccepted' => __('Vous devez accepter la procédure de gestion de la téléphonie pour soumettre.'),
            ]);
        }

        /** @var Routing|null $routing */
        $routing = null;

        $request = DB::transaction(function () use ($request, $actor, $policyAccepted, $comment, &$routing): ServiceRequest {
            /** @var ServiceRequest $request */
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $request->load([
                'beneficiary.department.direction', 'beneficiary.site', 'phoneDetail', 'simDetail',
                'profileDetail.department.direction', 'profileDetail.site', 'profileDetail.manager',
            ]);

            if ($request->requester_id !== $actor->id) {
                throw UnauthorizedActionException::notAllowedToRequestFor($request->beneficiaryName());
            }

            if (! $request->status->isEditableByRequester()) {
                throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::PendingManager);
            }

            $this->ensureHasContent($request);
            $this->ensureNoOtherOpenRequest($request);

            $manager = $this->approvers->managerForRequest($request);
            $initiatedByManager = $manager->id === $actor->id;
            $isResubmission = $request->status === RequestStatus::Returned;

            $request->fill([
                'reference' => $request->reference ?? $this->references->next($request->type),
                'beneficiary_snapshot' => $this->snapshot($request),
                'policy_accepted_at' => $policyAccepted ? now() : null,
                'submitted_at' => now(),
                'comment' => $comment ?: $request->comment,
            ])->save();

            if ($initiatedByManager) {
                $request->approvals()->create([
                    'step' => ApprovalStep::Manager,
                    'assigned_to_id' => $manager->id,
                    'decision' => ApprovalDecision::Approved,
                    'decided_by_id' => $actor->id,
                    'decided_at' => now(),
                    'comment' => __('Validée à la soumission : demande initiée par le manager.'),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
                $routing = $this->router->afterManager($request, $actor);
            } else {
                $approval = $request->approvals()->create(['step' => ApprovalStep::Manager, 'assigned_to_id' => $manager->id]);
                $routing = new Routing(RequestStatus::PendingManager, [$approval]);
            }

            $request->transitionTo(
                $routing->status,
                $actor,
                RequestEventType::Submitted,
                $isResubmission ? __('Demande corrigée et soumise à nouveau.') : $comment,
            );

            return $request;
        });

        foreach ($routing?->newApprovals ?? [] as $approval) {
            ApprovalRequested::dispatchTo($approval);
        }

        return $request;
    }

    private function ensureHasContent(ServiceRequest $request): void
    {
        $empty = match ($request->type) {
            RequestType::Telephony => ! $request->wantsPhone() && ! $request->wantsSim(),
            RequestType::UserProfile => $request->profileDetail?->action === null,
        };

        if ($empty) {
            throw ValidationException::withMessages(['subject' => __('La demande est incomplète : reprenez l\'assistant.')]);
        }
    }

    private function ensureNoOtherOpenRequest(ServiceRequest $request): void
    {
        if ($request->beneficiary_id === null) {
            return; // nouveau collaborateur : pas encore d'historique
        }

        $existing = ServiceRequest::query()
            ->where('beneficiary_id', $request->beneficiary_id)
            ->where('type', $request->type)
            ->whereKeyNot($request->getKey())
            ->whereIn('status', array_filter(RequestStatus::open(), fn (RequestStatus $status) => $status !== RequestStatus::Draft))
            ->first();

        if ($existing !== null) {
            throw DuplicateOpenRequestException::for($request->beneficiaryName(), $existing->displayReference());
        }
    }

    /**
     * Identité figée au moment de la soumission (la fiche reste exacte si la personne change de poste).
     *
     * @return array<string, mixed>
     */
    private function snapshot(ServiceRequest $request): array
    {
        if ($beneficiary = $request->beneficiary) {
            return [
                'matricule' => $beneficiary->matricule?->toStorage(),
                'first_name' => $beneficiary->first_name,
                'last_name' => $beneficiary->last_name,
                'nickname' => $beneficiary->nickname,
                'job_title' => $beneficiary->job_title,
                'category' => $beneficiary->category?->value,
                'department' => $beneficiary->department?->name,
                'direction' => $beneficiary->department?->direction?->code,
                'site' => $beneficiary->site?->name,
                'last_phone_assigned_at' => $beneficiary->lastPhoneAssignedAt()?->toDateString(),
            ];
        }

        $profile = $request->profileDetail;

        return [
            'matricule' => $profile?->matricule?->toStorage(),
            'first_name' => $profile?->first_name,
            'last_name' => $profile?->last_name,
            'job_title' => $profile?->job_title,
            'category' => $profile?->category?->value,
            'department' => $profile?->department?->name,
            'direction' => $profile?->department?->direction?->code,
            'site' => $profile?->site?->name,
            'newcomer' => true,
        ];
    }
}
