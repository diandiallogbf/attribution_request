<?php

namespace App\Models;

use App\Enums\LineOption;
use App\Enums\PlanType;
use App\Enums\SimLineStatus;
use App\ValueObjects\Iccid;
use App\ValueObjects\MoneyGnf;
use App\ValueObjects\PhoneNumber;
use Database\Factories\SimLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ligne de la flotte mobile.
 *
 * @property int $id
 * @property int $operator_id
 * @property PhoneNumber $msisdn
 * @property Iccid|null $iccid
 * @property PlanType $plan_type
 * @property Collection<int, LineOption>|null $options
 * @property MoneyGnf|null $monthly_recharge_gnf
 * @property bool $is_fleet
 * @property SimLineStatus $status
 * @property Carbon|null $activated_at
 * @property-read Operator $operator
 */
#[Fillable(['operator_id', 'msisdn', 'iccid', 'plan_type', 'options', 'monthly_recharge_gnf', 'is_fleet', 'status', 'activated_at'])]
class SimLine extends Model
{
    /** @use HasFactory<SimLineFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'msisdn' => PhoneNumber::class,
            'iccid' => Iccid::class,
            'plan_type' => PlanType::class,
            'options' => AsEnumCollection::of(LineOption::class),
            'monthly_recharge_gnf' => MoneyGnf::class,
            'is_fleet' => 'boolean',
            'status' => SimLineStatus::class,
            'activated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @return MorphMany<Assignment, $this> */
    public function assignments(): MorphMany
    {
        return $this->morphMany(Assignment::class, 'assignable');
    }

    /** @return MorphOne<Assignment, $this> */
    public function currentAssignment(): MorphOne
    {
        return $this->morphOne(Assignment::class, 'assignable')->whereNull('returned_at')->latestOfMany('assigned_at');
    }
}
