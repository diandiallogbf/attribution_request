<?php

namespace App\Models;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Entrée du journal d'une demande. Immuable : toute modification lève une exception.
 *
 * @property int $id
 * @property int $service_request_id
 * @property int|null $actor_id
 * @property RequestEventType $type
 * @property RequestStatus|null $from_status
 * @property RequestStatus|null $to_status
 * @property string|null $comment
 * @property array<string, mixed>|null $changes
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
#[Fillable(['service_request_id', 'actor_id', 'type', 'from_status', 'to_status', 'comment', 'changes'])]
class RequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => RequestEventType::class,
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal des demandes est immuable.'));
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
