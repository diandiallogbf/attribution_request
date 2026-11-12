<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Attribution d'un appareil ou d'une ligne à un collaborateur (historique complet).
 *
 * @property int $id
 * @property int|null $service_request_id
 * @property int $user_id
 * @property string $assignable_type
 * @property int $assignable_id
 * @property int|null $assigned_by_id
 * @property Carbon $assigned_at
 * @property array<string, bool>|null $setup_checklist
 * @property Carbon|null $received_at
 * @property string|null $receipt_signature_path
 * @property Carbon|null $policy_accepted_at
 * @property Carbon|null $returned_at
 * @property string|null $return_condition
 * @property string|null $notes
 * @property-read Device|SimLine $assignable
 */
#[Fillable([
    'service_request_id', 'user_id', 'assignable_type', 'assignable_id', 'assigned_by_id', 'assigned_at',
    'setup_checklist', 'received_at', 'receipt_signature_path', 'policy_accepted_at', 'returned_at', 'return_condition', 'notes',
])]
class Assignment extends Model
{
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'setup_checklist' => 'array',
            'received_at' => 'datetime',
            'policy_accepted_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Device|SimLine, $this> */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('returned_at');
    }
}
