<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\AttachmentKind;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\Role;
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
use Illuminate\Support\Str;

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
 * @property int|null $beneficiary_id
 * @property array<string, mixed>|null $beneficiary_snapshot
 * @property string|null $comment
 * @property Carbon|null $policy_accepted_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $executed_at
 * @property int|null $executed_by_id
 * @property string|null $execution_notes
 * @property int|null $handler_id
 * @property int|null $delegated_by_id
 * @property Carbon|null $delegated_at
 * @property string|null $delegation_note
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $requester
 * @property-read User|null $beneficiary
 * @property-read User|null $executedBy
 * @property-read User|null $handler
 * @property-read User|null $delegatedBy
 * @property-read PhoneRequestDetail|null $phoneDetail
 * @property-read SimRequestDetail|null $simDetail
 * @property-read ProfileRequestDetail|null $profileDetail
 */
#[Fillable([
    'reference', 'type', 'status', 'site_id', 'requester_id', 'beneficiary_id', 'beneficiary_snapshot',
    'comment', 'policy_accepted_at', 'submitted_at', 'executed_at', 'executed_by_id', 'execution_notes',
    'handler_id', 'delegated_by_id', 'delegated_at', 'delegation_note', 'closed_at',
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
            'executed_at' => 'datetime',
            'delegated_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_id');
    }

    /** Membre de l'équipe IT à qui la DSI a confié la commande / l'exécution. @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    /** @return BelongsTo<User, $this> */
    public function delegatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by_id');
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

    /** @return HasOne<ProfileRequestDetail, $this> */
    public function profileDetail(): HasOne
    {
        return $this->hasOne(ProfileRequestDetail::class);
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

    /** Fiche officielle archivée (PDF) à la clôture. @return MorphMany<Attachment, $this> */
    public function archivedForms(): MorphMany
    {
        return $this->attachments()->where('kind', AttachmentKind::RequestForm)->latest('id');
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

    /** Demandes confiées à cet utilisateur et encore à traiter. @param  Builder<self>  $query */
    public function scopeHandledBy(Builder $query, User $user): void
    {
        $query->where('handler_id', $user->id)->whereIn('status', RequestStatus::fulfilment());
    }

    // ── Comportement ─────────────────────────────────────────────────────────

    /**
     * Qui peut commander, réceptionner, attribuer ou exécuter : toute l'équipe IT tant que la DSI
     * n'a rien délégué ; une fois déléguée, seulement le délégataire (et la DSI / l'admin qui gardent la main).
     */
    public function canBeFulfilledBy(User $user): bool
    {
        if (! $user->hasAnyRole(Role::fulfilment())) {
            return false;
        }

        return $this->handler_id === null
            || $this->handler_id === $user->id
            || $user->hasAnyRole(Role::delegators());
    }

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

    /** Assistant à utiliser pour reprendre ou corriger la demande. */
    public function editUrl(): string
    {
        return $this->type === RequestType::UserProfile
            ? route('requests.profile.edit', $this)
            : route('requests.edit', $this);
    }

    /** Nom du bénéficiaire, y compris un nouveau collaborateur sans compte (création de profil). */
    public function beneficiaryName(): string
    {
        if ($this->beneficiary_id !== null && $this->beneficiary !== null) {
            return $this->beneficiary->name;
        }

        $first = $this->beneficiary_snapshot['first_name'] ?? $this->profileDetail?->first_name;
        $last = $this->beneficiary_snapshot['last_name'] ?? $this->profileDetail?->last_name;

        return trim($first.' '.Str::upper((string) $last)) ?: __('Nouveau collaborateur');
    }

    public function beneficiaryInitials(): string
    {
        return collect(explode(' ', $this->beneficiaryName()))
            ->filter()
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->take(2)
            ->join('');
    }

    public function wantsPhone(): bool
    {
        return $this->phoneDetail !== null;
    }

    public function wantsSim(): bool
    {
        return $this->simDetail !== null;
    }

    /** « Téléphone + ligne SIM », « Création de profil »… */
    public function subjectLabel(): string
    {
        if ($this->type === RequestType::UserProfile) {
            return $this->profileDetail?->action
                ? __(':action de profil', ['action' => $this->profileDetail->action->getLabel()])
                : __('Profil utilisateur');
        }

        return match (true) {
            $this->wantsPhone() && $this->wantsSim() => __('Téléphone + ligne SIM'),
            $this->wantsPhone() => __('Téléphone'),
            $this->wantsSim() => __('Ligne SIM'),
            default => $this->type->getLabel(),
        };
    }

    public function subjectIcon(): string
    {
        return match (true) {
            $this->type === RequestType::UserProfile => $this->profileDetail?->action?->icon() ?? 'identification',
            $this->wantsPhone() && $this->wantsSim() => 'squares-plus',
            $this->wantsSim() => 'signal',
            default => 'device-phone-mobile',
        };
    }

    public function pendingApproval(): ?RequestApproval
    {
        return $this->approvals->first(fn (RequestApproval $approval) => $approval->isPending());
    }

    /** Validation en attente qui revient à cet utilisateur (plusieurs directeurs peuvent valider en parallèle). */
    public function pendingApprovalFor(User $user): ?RequestApproval
    {
        return $this->approvals->first(
            fn (RequestApproval $approval) => $approval->decision === ApprovalDecision::Pending && $approval->isAssignedTo($user),
        );
    }
}
