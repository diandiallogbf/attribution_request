<?php

namespace App\Models;

use App\Enums\LineOption;
use App\Enums\PlanType;
use App\Enums\SimAction;
use App\ValueObjects\MoneyGnf;
use App\ValueObjects\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $service_request_id
 * @property SimAction|null $action
 * @property PlanType|null $plan_type
 * @property Collection<int, LineOption>|null $options
 * @property int|null $existing_sim_line_id
 * @property PhoneNumber|null $existing_msisdn
 * @property string|null $entity
 * @property MoneyGnf|null $requested_monthly_recharge_gnf
 * @property MoneyGnf|null $approved_monthly_recharge_gnf
 * @property-read SimLine|null $existingSimLine
 */
#[Fillable([
    'service_request_id', 'action', 'plan_type', 'options', 'existing_sim_line_id', 'existing_msisdn',
    'entity', 'requested_monthly_recharge_gnf', 'approved_monthly_recharge_gnf',
])]
class SimRequestDetail extends Model
{
    protected function casts(): array
    {
        return [
            'action' => SimAction::class,
            'plan_type' => PlanType::class,
            'options' => AsEnumCollection::of(LineOption::class),
            'existing_msisdn' => PhoneNumber::class,
            'requested_monthly_recharge_gnf' => MoneyGnf::class,
            'approved_monthly_recharge_gnf' => MoneyGnf::class,
        ];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<SimLine, $this> */
    public function existingSimLine(): BelongsTo
    {
        return $this->belongsTo(SimLine::class, 'existing_sim_line_id');
    }
}
