<?php

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\Concerns\EnsuresRole;
use App\Enums\DeviceStatus;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\SimAction;
use App\Enums\SimLineStatus;
use App\Exceptions\Domain\FulfilmentException;
use App\Models\Device;
use App\Models\ServiceRequest;
use App\Models\SimLine;
use App\Models\User;

/** Étape 04 sans commande : le matériel nécessaire est déjà en stock. */
final class ServeFromStock
{
    use EnsuresRole;

    public function handle(ServiceRequest $request, User $actor): ServiceRequest
    {
        $this->ensureRole($actor, Role::fulfilment(), __('servir une demande depuis le stock'));
        $request->loadMissing(['phoneDetail.approvedDeviceModel', 'simDetail']);

        $model = $request->phoneDetail?->approvedDeviceModel;

        if ($request->phoneDetail?->reason?->requiresNewDevice() && $model !== null
            && ! Device::query()->where('device_model_id', $model->id)->where('status', DeviceStatus::InStock)->exists()) {
            throw FulfilmentException::outOfStock($model->label);
        }

        if ($request->simDetail?->action === SimAction::Creation
            && ! SimLine::query()->where('status', SimLineStatus::InStock)->exists()) {
            throw FulfilmentException::outOfStock(__('puce SIM'));
        }

        $request->transitionTo(RequestStatus::ReadyForAssignment, $actor, RequestEventType::Received, __('Matériel disponible en stock.'));

        return $request;
    }
}
