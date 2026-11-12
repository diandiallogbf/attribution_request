<?php

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\Concerns\EnsuresRole;
use App\Data\TelephonyFulfilmentData;
use App\Enums\DeviceStatus;
use App\Enums\PhoneReason;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\SimAction;
use App\Enums\SimLineStatus;
use App\Exceptions\Domain\DeviceUnavailableException;
use App\Exceptions\Domain\FulfilmentException;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Models\Assignment;
use App\Models\Device;
use App\Models\ServiceRequest;
use App\Models\SimLine;
use App\Models\SimRequestDetail;
use App\Models\User;
use App\Notifications\RequestMilestone;
use Illuminate\Support\Facades\DB;

/**
 * Étape 05 — Attribution : l'IT configure et remet le matériel, enregistre le retour de l'ancien appareil
 * et applique l'action demandée sur la ligne. S'il y a une remise, le bénéficiaire doit confirmer la réception ;
 * sinon (restitution, résiliation, modification…) la demande est clôturée directement.
 */
final readonly class FulfilTelephonyRequest
{
    use EnsuresRole;

    public function __construct(private CloseServiceRequest $close) {}

    public function handle(ServiceRequest $request, User $actor, TelephonyFulfilmentData $data): ServiceRequest
    {
        $this->ensureRole($actor, Role::fulfilment(), __('attribuer du matériel'));

        $handover = false;

        $request = DB::transaction(function () use ($request, $actor, $data, &$handover): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $request->load(['beneficiary', 'phoneDetail.previousDevice', 'simDetail.existingSimLine']);
            $this->ensureCanFulfil($request, $actor, __('attribuer du matériel'));

            if (! in_array($request->status, [RequestStatus::Approved, RequestStatus::ReadyForAssignment], true)) {
                throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::PendingReceipt);
            }

            $summary = [];

            if ($phone = $request->phoneDetail) {
                if ($phone->reason?->requiresNewDevice()) {
                    $device = $this->takeFromStock($data->deviceId);
                    $this->assign($request, $actor, $device, $data->setupChecklist);
                    $summary[] = __('Téléphone remis : :label', ['label' => $device->label]);
                    $handover = true;
                }

                if ($phone->reason?->requiresPreviousDevice() && $phone->previousDevice) {
                    $this->handlePreviousDevice($phone->previousDevice, $phone->reason, $data);
                    $summary[] = __('Ancien téléphone : :status', ['status' => ($phone->reason === PhoneReason::Repair ? DeviceStatus::Assigned : ($data->previousDeviceStatus ?? DeviceStatus::InStock))->getLabel()]);
                }
            }

            if ($sim = $request->simDetail) {
                [$handedOver, $line] = $this->handleLine($request, $actor, $sim, $data);
                $handover = $handover || $handedOver;
                $summary[] = __('Ligne :number : :action', ['number' => $line->msisdn->international(), 'action' => $sim->action->getLabel()]);
            }

            $request->fill([
                'executed_at' => now(),
                'executed_by_id' => $actor->id,
                'execution_notes' => $data->notes,
            ])->save();

            $comment = implode(' · ', $summary);

            if ($handover) {
                $request->transitionTo(RequestStatus::PendingReceipt, $actor, RequestEventType::Assigned, $comment);
            } else {
                $request->recordEvent(RequestEventType::Executed, $actor, $comment);
            }

            return $request;
        });

        if ($handover) {
            $request->beneficiary?->notify(new RequestMilestone(
                $request,
                __('Votre équipement est prêt'),
                __('La DSI a préparé votre matériel (:subject). Récupérez-le puis confirmez la réception dans le portail.', ['subject' => $request->subjectLabel()]),
                __('Confirmer la réception'),
            ));

            return $request;
        }

        return $this->close->handle($request, $actor, __('Clôturée après exécution (aucune remise de matériel).'));
    }

    private function takeFromStock(?int $deviceId): Device
    {
        if ($deviceId === null) {
            throw FulfilmentException::missing(__('appareil à remettre'));
        }

        /** @var Device $device */
        $device = Device::query()->whereKey($deviceId)->lockForUpdate()->with('model')->firstOrFail();

        if ($device->status !== DeviceStatus::InStock) {
            throw DeviceUnavailableException::alreadyAssigned($device->label);
        }

        return $device;
    }

    /**
     * @param  array<string, bool>  $checklist
     */
    private function assign(ServiceRequest $request, User $actor, Device|SimLine $item, array $checklist = []): void
    {
        Assignment::create([
            'service_request_id' => $request->id,
            'user_id' => $request->beneficiary_id,
            'assignable_type' => $item->getMorphClass(),
            'assignable_id' => $item->id,
            'assigned_by_id' => $actor->id,
            'assigned_at' => now(),
            'setup_checklist' => $checklist === [] ? null : $checklist,
        ]);

        if ($item instanceof Device) {
            $item->update(['status' => DeviceStatus::Assigned]);
        }
    }

    private function handlePreviousDevice(Device $device, PhoneReason $reason, TelephonyFulfilmentData $data): void
    {
        if ($reason === PhoneReason::Repair) {
            return; // réparé et rendu : l'attribution en cours reste valable
        }

        $device->assignments()->whereNull('returned_at')->update([
            'returned_at' => now(),
            'return_condition' => $data->returnCondition,
        ]);

        $device->update(['status' => $data->previousDeviceStatus ?? DeviceStatus::InStock]);
    }

    /**
     * @return array{0: bool, 1: SimLine} [remise au bénéficiaire ?, ligne concernée]
     */
    private function handleLine(ServiceRequest $request, User $actor, SimRequestDetail $sim, TelephonyFulfilmentData $data): array
    {
        $plan = [
            'plan_type' => $sim->plan_type,
            'options' => $sim->options?->all() ?? [],
            'monthly_recharge_gnf' => $sim->approved_monthly_recharge_gnf ?? $sim->requested_monthly_recharge_gnf,
        ];

        if ($sim->action === SimAction::Creation) {
            if ($data->simLineId === null) {
                throw FulfilmentException::missing(__('ligne à attribuer'));
            }

            /** @var SimLine $line */
            $line = SimLine::query()->whereKey($data->simLineId)->lockForUpdate()->firstOrFail();

            if ($line->status !== SimLineStatus::InStock) {
                throw DeviceUnavailableException::alreadyAssigned($line->msisdn->international());
            }

            $line->update($plan + ['status' => SimLineStatus::Active, 'activated_at' => now()]);
            $this->assign($request, $actor, $line);

            return [true, $line];
        }

        $line = $sim->existingSimLine
            ?? ($sim->existing_msisdn ? SimLine::query()->where('msisdn', $sim->existing_msisdn->toStorage())->first() : null)
            ?? throw FulfilmentException::lineNotInInventory($sim->existing_msisdn?->international() ?? '—');

        $closeCurrent = fn () => $line->assignments()->whereNull('returned_at')->update(['returned_at' => now()]);

        return match ($sim->action) {
            SimAction::Transfer => (function () use ($request, $actor, $line, $plan, $closeCurrent) {
                $closeCurrent();
                $line->update($plan + ['status' => SimLineStatus::Active]);
                $this->assign($request, $actor, $line);

                return [true, $line];
            })(),
            SimAction::Modification => (function () use ($line, $plan) {
                $line->update($plan);

                return [false, $line];
            })(),
            SimAction::Return => (function () use ($line, $closeCurrent) {
                $closeCurrent();
                $line->update(['status' => SimLineStatus::InStock]);

                return [false, $line];
            })(),
            SimAction::Deletion => (function () use ($line, $closeCurrent) {
                $closeCurrent();
                $line->update(['status' => SimLineStatus::Terminated]);

                return [false, $line];
            })(),
        };
    }
}
