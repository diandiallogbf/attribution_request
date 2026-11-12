<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Validation attendue ou rendue. Avec l'utilisateur authentifié, l'horodatage, l'IP et le navigateur,
 * elle tient lieu de signature électronique simple du valideur.
 *
 * @property int $id
 * @property int $service_request_id
 * @property ApprovalStep $step
 * @property int|null $assigned_to_id
 * @property string|null $assigned_role
 * @property ApprovalDecision $decision
 * @property int|null $decided_by_id
 * @property Carbon|null $decided_at
 * @property string|null $comment
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int|null $direction_id
 * @property Carbon|null $reminded_at
 * @property-read Direction|null $direction
 * @property-read ServiceRequest $serviceRequest
 * @property-read User|null $assignedTo
 * @property-read User|null $decidedBy
 */
#[Fillable([
    'service_request_id', 'step', 'assigned_to_id', 'assigned_role', 'direction_id', 'decision',
    'decided_by_id', 'decided_at', 'comment', 'ip_address', 'user_agent', 'reminded_at',
])]
class RequestApproval extends Model
{
    protected $attributes = [
        'decision' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'step' => ApprovalStep::class,
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** Direction concernée par une validation métier. @return BelongsTo<Direction, $this> */
    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    /** « Validation métier — DT » */
    public function label(): string
    {
        return $this->direction
            ? $this->step->getLabel().' — '.$this->direction->code
            : $this->step->getLabel();
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function isPending(): bool
    {
        return $this->decision === ApprovalDecision::Pending;
    }

    /** L'utilisateur est-il le valideur attendu (nominatif ou par rôle) ? */
    public function isAssignedTo(User $user): bool
    {
        if ($this->assigned_to_id !== null) {
            return $this->assigned_to_id === $user->id;
        }

        return $this->assigned_role !== null && $user->hasRole($this->assigned_role);
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('decision', ApprovalDecision::Pending);
    }

    /** Validations en attente qui reviennent à cet utilisateur. @param  Builder<self>  $query */
    public function scopeAwaiting(Builder $query, User $user): void
    {
        $query->pending()->where(function (Builder $q) use ($user): void {
            $q->where('assigned_to_id', $user->id)
                ->orWhere(fn (Builder $byRole) => $byRole
                    ->whereNull('assigned_to_id')
                    ->whereIn('assigned_role', $user->getRoleNames()));
        });
    }
}
