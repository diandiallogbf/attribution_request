<?php

namespace App\Models;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Exceptions\Domain\InvalidTransitionException;
use App\ValueObjects\RequestReference;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Demande (agrégat racine). Le statut ne change QUE via transitionTo(),
 * qui vérifie le graphe de RequestStatus et écrit le journal.
 *
 * @property int $id
 * @property string $uuid
 * @property RequestReference|null $reference
 * @property RequestType $type
 * @property RequestStatus $status
 * @property int|null $site_id
 * @property int $requester_id
 * @property int $beneficiary_id
 * @property array<string, mixed>|null $beneficiary_snapshot
 * @property string|null $comment
 * @property Carbon|null $policy_accepted_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $requester
 * @property-read User $beneficiary
 * @property-read PhoneRequestDetail|null $phoneDetail
 * @property-read SimRequestDetail|null $simDetail
 */
#[Fillable([
    'reference', 'type', 'status', 'site_id', 'requester_id', 'beneficiary_id', 'beneficiary_snapshot',
    'comment', 'policy_accepted_at', 'submitted_at', 'closed_at',
])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'reference' => RequestReference::class,
            'type' => RequestType::class,
            'status' => RequestStatus::class,
            'beneficiary_snapshot' => 'array',
            'policy_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** L'uuid sert d'identifiant public (URL) ; l'id auto-incrémenté reste interne. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasOne<PhoneRequestDetail, $this> */
    public function phoneDetail(): HasOne
    {
        return $this->hasOne(PhoneRequestDetail::class);
    }

    /** @return HasOne<SimRequestDetail, $this> */
    public function simDetail(): HasOne
    {
        return $this->hasOne(SimRequestDetail::class);
    }

    /** @return HasMany<RequestApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class);
    }

    /** @return HasMany<RequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', RequestStatus::open());
    }

    /** Demandes que l'utilisateur a faites ou dont il est bénéficiaire. @param  Builder<self>  $query */
    public function scopeInvolving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('requester_id', $user->id)->orWhere('beneficiary_id', $user->id));
    }

    // ── Comportement ─────────────────────────────────────────────────────────

    /**
     * Seul point d'entrée pour changer le statut.
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws InvalidTransitionException
     */
    public function transitionTo(
        RequestStatus $target,
        ?User $actor,
        RequestEventType $event,
        ?string $comment = null,
        array $changes = [],
    ): void {
        $from = $this->status;

        if (! $from->canTransitionTo($target)) {
            throw InvalidTransitionException::between($this->displayReference(), $from, $target);
        }

        $this->status = $target;

        if ($target === RequestStatus::Closed) {
            $this->closed_at = now();
        }

        $this->save();

        $this->recordEvent($event, $actor, $comment, $changes, $from, $target);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function recordEvent(
        RequestEventType $type,
        ?User $actor,
        ?string $comment = null,
        array $changes = [],
        ?RequestStatus $from = null,
        ?RequestStatus $to = null,
    ): RequestEvent {
        return $this->events()->create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'comment' => $comment,
            'changes' => $changes === [] ? null : $changes,
        ]);
    }

    public function displayReference(): string
    {
        return $this->reference?->toStorage() ?? __('Brouillon');
    }

    public function wantsPhone(): bool
    {
        return $this->phoneDetail !== null;
    }

    public function wantsSim(): bool
    {
        return $this->simDetail !== null;
    }

    /** « Téléphone + ligne SIM » */
    public function subjectLabel(): string
    {
        return match (true) {
            $this->wantsPhone() && $this->wantsSim() => __('Téléphone + ligne SIM'),
            $this->wantsPhone() => __('Téléphone'),
            $this->wantsSim() => __('Ligne SIM'),
            default => $this->type->getLabel(),
        };
    }

    public function pendingApproval(): ?RequestApproval
    {
        return $this->approvals->first(fn (RequestApproval $approval) => $approval->isPending());
    }
}
