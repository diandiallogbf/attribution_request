<?php

namespace App\Models;

use App\Enums\PhoneReason;
use App\Enums\PhoneType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_request_id
 * @property PhoneReason|null $reason
 * @property PhoneType|null $requested_phone_type
 * @property string|null $other_type_label
 * @property string|null $detailed_reason
 * @property int|null $previous_device_id
 * @property string|null $previous_device_label
 * @property string|null $previous_device_serial
 * @property int|null $approved_device_model_id
 * @property bool|null $allowance_compliant
 * @property string|null $allowance_override_reason
 * @property-read Device|null $previousDevice
 * @property-read DeviceModel|null $approvedDeviceModel
 */
#[Fillable([
    'service_request_id', 'reason', 'requested_phone_type', 'other_type_label', 'detailed_reason',
    'previous_device_id', 'previous_device_label', 'previous_device_serial',
    'approved_device_model_id', 'allowance_compliant', 'allowance_override_reason',
])]
class PhoneRequestDetail extends Model
{
    protected function casts(): array
    {
        return [
            'reason' => PhoneReason::class,
            'requested_phone_type' => PhoneType::class,
            'allowance_compliant' => 'boolean',
        ];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function previousDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'previous_device_id');
    }

    /** @return BelongsTo<DeviceModel, $this> */
    public function approvedDeviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class, 'approved_device_model_id');
    }

    public function requestedTypeLabel(): ?string
    {
        if ($this->requested_phone_type === PhoneType::Other && $this->other_type_label) {
            return $this->other_type_label;
        }

        return $this->requested_phone_type?->getLabel();
    }
}
