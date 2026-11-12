<?php

namespace App\Services\Workflow;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\Role;
use App\Exceptions\Domain\MissingApproverException;
use App\Models\Application;
use App\Models\Direction;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Aiguillage du circuit de validation, commun aux deux fiches :
 *   manager → [validation métier : un directeur par direction concernée, tous requis] → DSI.
 * La validation métier n'existe que pour la fiche profil (règle imprimée sur la fiche F-0008).
 */
final class WorkflowRouter
{
    /** Après la validation du manager (rendue ou d'office). */
    public function afterManager(ServiceRequest $request, ?User $manager): Routing
    {
        $directions = $this->businessDirections($request);

        if ($directions->isEmpty()) {
            return $this->toDsi($request);
        }

        $created = [];

        foreach ($directions as $direction) {
            $director = $direction->director ?? throw MissingApproverException::noDirector($direction->name);

            // Le directeur qui vient de valider en tant que manager n'a pas à signer une seconde fois.
            $alreadySigned = $manager !== null && $director->id === $manager->id;

            $approval = $request->approvals()->create([
                'step' => ApprovalStep::Business,
                'assigned_to_id' => $director->id,
                'direction_id' => $direction->id,
                'decision' => $alreadySigned ? ApprovalDecision::Approved : ApprovalDecision::Pending,
                'decided_by_id' => $alreadySigned ? $manager->id : null,
                'decided_at' => $alreadySigned ? now() : null,
                'comment' => $alreadySigned ? __('Validée d\'office : le directeur a validé en tant que manager.') : null,
            ]);

            if (! $alreadySigned) {
                $created[] = $approval;
            }
        }

        return $created === [] ? $this->toDsi($request) : new Routing(RequestStatus::PendingBusiness, $created);
    }

    /** Après une validation métier : la DSI n'est saisie que lorsque tous les directeurs ont validé. */
    public function afterBusiness(ServiceRequest $request): ?Routing
    {
        $stillPending = $request->approvals()
            ->where('step', ApprovalStep::Business)
            ->where('decision', ApprovalDecision::Pending)
            ->exists();

        return $stillPending ? null : $this->toDsi($request);
    }

    public function toDsi(ServiceRequest $request): Routing
    {
        $approval = $request->approvals()->create([
            'step' => ApprovalStep::Dsi,
            'assigned_role' => Role::Dsi->value,
        ]);

        return new Routing(RequestStatus::PendingDsi, [$approval]);
    }

    /** Les autres validations encore attendues deviennent sans objet (renvoi, rejet, annulation). */
    public function skipPending(ServiceRequest $request, string $reason, ?RequestApproval $except = null): void
    {
        $request->approvals()
            ->where('decision', ApprovalDecision::Pending)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->update(['decision' => ApprovalDecision::Skipped, 'comment' => $reason, 'updated_at' => now()]);
    }

    /**
     * Directions dont le directeur doit valider (applications cochées soumises à validation métier).
     *
     * @return Collection<int, Direction>
     */
    public function businessDirections(ServiceRequest $request): Collection
    {
        if ($request->type !== RequestType::UserProfile || $request->profileDetail === null) {
            return collect();
        }

        return $request->profileDetail->applications()
            ->with('ownerDirection.director')
            ->get()
            ->filter(fn (Application $application) => $application->needsBusinessApproval())
            ->map(fn (Application $application) => $application->ownerDirection)
            ->unique('id')
            ->values();
    }
}
