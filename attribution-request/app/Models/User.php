<?php

namespace App\Models;

use App\Enums\EmployeeCategory;
use App\Enums\Role;
use App\ValueObjects\Matricule;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property Matricule|null $matricule
 * @property string $first_name
 * @property string $last_name
 * @property string|null $nickname
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $job_title
 * @property EmployeeCategory|null $category
 * @property int|null $site_id
 * @property int|null $department_id
 * @property int|null $manager_id
 * @property string|null $azure_oid
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $name
 * @property-read Department|null $department
 * @property-read Site|null $site
 * @property-read User|null $manager
 */
#[Fillable([
    'matricule', 'first_name', 'last_name', 'nickname', 'email', 'password', 'job_title', 'category',
    'site_id', 'department_id', 'manager_id', 'azure_oid', 'is_active', 'last_login_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'matricule' => Matricule::class,
            'category' => EmployeeCategory::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ── Identité ─────────────────────────────────────────────────────────────

    /** Nom complet « Prénom NOM » — conserve la compatibilité avec le code qui lit $user->name. */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.Str::upper($this->last_name)));
    }

    public function initials(): string
    {
        return Str::upper(Str::substr($this->first_name, 0, 1).Str::substr($this->last_name, 0, 1));
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole(Role::backOffice());
    }

    // ── Organisation ─────────────────────────────────────────────────────────

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** N+1 hiérarchique. @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Collaborateurs directs. @return HasMany<User, $this> */
    public function directReports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function isManagerOf(User $other): bool
    {
        return $other->manager_id === $this->id;
    }

    public function isManager(): bool
    {
        return $this->directReports()->exists();
    }

    // ── Demandes & parc ──────────────────────────────────────────────────────

    /** @return HasMany<ServiceRequest, $this> */
    public function requestsAsBeneficiary(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'beneficiary_id');
    }

    /** @return HasMany<ServiceRequest, $this> */
    public function requestsAsRequester(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'requester_id');
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('returned_at');
    }

    /** Date d'attribution du dernier téléphone (calculée depuis le parc, plus de saisie manuelle). */
    public function lastPhoneAssignedAt(): ?CarbonInterface
    {
        $assignedAt = $this->assignments()
            ->where('assignable_type', (new Device)->getMorphClass())
            ->max('assigned_at');

        return $assignedAt ? Date::parse($assignedAt) : null;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
