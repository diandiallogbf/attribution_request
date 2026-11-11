<?php

namespace App\Actions\Requests;

use App\Enums\ApprovalDecision;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Models\DeviceModel;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\AllowanceChecker;
use App\ValueObjects\MoneyGnf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Étape 03 -Approbation DSI : vérifie la grille (modèle, type, recharge), fixe le modèle retenu
 * et le montant approuvé, puis valide. Tout écart à la grille exige une justification (dérogation tracée).
 */
final readonly class ApproveByDsi
{
    public function __construct(
        private DecideApproval $decide,
        private AllowanceChecker $allowances,
    ) {}

    public function handle(
        ServiceRequest $request,
        User $dsi,
        ?DeviceModel $approvedModel,
        ?MoneyGnf $approvedRecharge,
        ?string $comment = null,
    ): ServiceRequest {
        $request->loadMissing(['beneficiary', 'phoneDetail', 'simDetail']);

        if ($request->status !== RequestStatus::PendingDsi) {
            throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::Approved);
        }

        $phone = $request->phoneDetail;
        $sim = $request->simDetail;
        $needsDevice = (bool) $phone?->reason?->requiresNewDevice();
        $needsPlan = (bool) $sim?->action?->requiresPlan();

        if ($needsDevice && $approvedModel === null) {
            throw ValidationException::withMessages(['approved_device_model_id' => __('Choisissez le modèle à attribuer.')]);
        }

        if ($needsPlan && $approvedRecharge === null) {
            throw ValidationException::withMessages(['approved_monthly_recharge_gnf' => __('Fixez le montant de recharge mensuelle.')]);
        }

        $check = $this->allowances->check(
            $request->beneficiary,
            $needsDevice ? $approvedModel->phone_type : null,
            $needsPlan ? $approvedRecharge : null,
        );

        if (! $check->isCompliant() && blank($comment)) {
            throw ValidationException::withMessages([
                'comment' => __('Hors grille de dotation : justifiez la dérogation (:details).', ['details' => implode(' ', $check->violations)]),
            ]);
        }

        return DB::transaction(function () use ($request, $dsi, $phone, $sim, $needsDevice, $needsPlan, $approvedModel, $approvedRecharge, $check, $comment) {
            if ($needsDevice) {
                $phone->update([
                    'approved_device_model_id' => $approvedModel->id,
                    'allowance_compliant' => $check->isCompliant(),
                    'allowance_override_reason' => $check->isCompliant() ? null : $comment,
                ]);
            }

            if ($needsPlan) {
                $sim->update(['approved_monthly_recharge_gnf' => $approvedRecharge]);
            }

            return $this->decide->handle($request, $dsi, ApprovalDecision::Approved, $comment);
        });
    }
}
