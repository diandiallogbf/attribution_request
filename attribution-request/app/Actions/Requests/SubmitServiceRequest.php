<?php

namespace App\Actions\Requests;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Exceptions\Domain\DuplicateOpenRequestException;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\Services\ApproverResolver;
use App\Services\RequestReferenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soumission (ou re-soumission après renvoi).
 * - Si le demandeur est lui-même le N+1 du bénéficiaire, l'étape manager est validée d'office → DSI.
 * - Sinon la demande part chez le N+1.
 */
final readonly class SubmitServiceRequest
{
    public function __construct(
        private ApproverResolver $approvers,
        private RequestReferenceGenerator $references,
    ) {}

    public function handle(ServiceRequest $request, User $actor, bool $policyAccepted, ?string $comment = null): ServiceRequest
    {
        if (! $policyAccepted) {
            throw ValidationException::withMessages([
                'policyAccepted' => __('Vous devez accepter la procédure de gestion de la téléphonie pour soumettre.'),
            ]);
        }

        $notify = null;

        $request = DB::transaction(function () use ($request, $actor, $comment, &$notify): ServiceRequest {
            /** @var ServiceRequest $request */
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $request->load(['beneficiary.department.direction', 'beneficiary.site', 'phoneDetail', 'simDetail']);

            if ($request->requester_id !== $actor->id) {
                throw UnauthorizedActionException::notAllowedToRequestFor($request->beneficiary->name);
            }

            if (! $request->status->isEditableByRequester()) {
                throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::PendingManager);
            }

            if (! $request->wantsPhone() && ! $request->wantsSim()) {
                throw ValidationException::withMessages(['subject' => __('La demande doit porter sur un téléphone et/ou une ligne SIM.')]);
            }

            $this->ensureNoOtherOpenRequest($request);

            $beneficiary = $request->beneficiary;
            $manager = $this->approvers->managerFor($beneficiary);
            $initiatedByManager = $manager->id === $actor->id;
            $isResubmission = $request->status === RequestStatus::Returned;

            $request->fill([
                'reference' => $request->reference ?? $this->references->next($request->type),
                'beneficiary_snapshot' => $this->snapshot($beneficiary),
                'policy_accepted_at' => now(),
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
                $notify = $request->approvals()->create(['step' => ApprovalStep::Dsi, 'assigned_role' => Role::Dsi->value]);
                $target = RequestStatus::PendingDsi;
            } else {
                $notify = $request->approvals()->create(['step' => ApprovalStep::Manager, 'assigned_to_id' => $manager->id]);
                $target = RequestStatus::PendingManager;
            }

            $request->transitionTo(
                $target,
                $actor,
                RequestEventType::Submitted,
                $isResubmission ? __('Demande corrigée et soumise à nouveau.') : $comment,
            );

            return $request;
        });

        $notify?->loadMissing('serviceRequest');
        if ($notify !== null) {
            ApprovalRequested::dispatchTo($notify);
        }

        return $request;
    }

    private function ensureNoOtherOpenRequest(ServiceRequest $request): void
    {
        $existing = ServiceRequest::query()
            ->where('beneficiary_id', $request->beneficiary_id)
            ->where('type', $request->type)
            ->whereKeyNot($request->getKey())
            ->whereIn('status', array_filter(RequestStatus::open(), fn (RequestStatus $status) => $status !== RequestStatus::Draft))
            ->first();

        if ($existing !== null) {
            throw DuplicateOpenRequestException::for($request->beneficiary->name, $existing->displayReference());
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(User $beneficiary): array
    {
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
}
