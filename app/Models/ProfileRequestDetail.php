<?php

namespace App\Models;

use App\Enums\EmployeeCategory;
use App\Enums\EquipmentType;
use App\Enums\ProfileAction;
use App\Enums\ProfileType;
use App\ValueObjects\Matricule;
use App\ValueObjects\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Détail de la fiche profil utilisateur (AGL-GIN-DSI-F-0008).
 *
 * @property int $id
 * @property int $service_request_id
 * @property ProfileAction|null $action
 * @property ProfileType|null $profile_type
 * @property Carbon|null $internship_ends_at
 * @property Carbon|null $effective_at
 * @property string|null $first_name
 * @property string|null $last_name
 * @property Matricule|null $matricule
 * @property string|null $job_title
 * @property EmployeeCategory|null $category
 * @property int|null $department_id
 * @property int|null $site_id
 * @property int|null $manager_id
 * @property string|null $reference_profile
 * @property bool $needs_email
 * @property string|null $distribution_lists
 * @property bool $needs_as400
 * @property bool $needs_internet
 * @property string|null $other_application
 * @property Collection<int, EquipmentType>|null $equipment
 * @property PhoneNumber|null $gsm_number
 * @property string|null $comment
 * @property string|null $execution_ticket
 * @property int|null $created_user_id
 * @property-read Collection<int, Application> $applications
 * @property-read Department|null $department
 * @property-read Site|null $site
 * @property-read User|null $manager
 */
#[Fillable([
    'service_request_id', 'action', 'profile_type', 'internship_ends_at', 'effective_at',
    'first_name', 'last_name', 'matricule', 'job_title', 'category', 'department_id', 'site_id', 'manager_id',
    'reference_profile', 'needs_email', 'distribution_lists', 'needs_as400', 'needs_internet', 'other_application',
    'equipment', 'gsm_number', 'comment', 'execution_ticket', 'created_user_id',
])]
class ProfileRequestDetail extends Model
{
    protected function casts(): array
    {
        return [
            'action' => ProfileAction::class,
            'profile_type' => ProfileType::class,
            'internship_ends_at' => 'date',
            'effective_at' => 'date',
            'matricule' => Matricule::class,
            'category' => EmployeeCategory::class,
            'needs_email' => 'boolean',
            'needs_as400' => 'boolean',
            'needs_internet' => 'boolean',
            'equipment' => AsEnumCollection::of(EquipmentType::class),
            'gsm_number' => PhoneNumber::class,
        ];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsToMany<Application, $this> */
    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'profile_request_applications')
            ->withPivot('executed_at')
            ->withTimestamps()
            ->orderBy('sort_order');
    }

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

    /** Futur N+1 du nouveau collaborateur (valide l'étape 2). @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Liste lisible des accès de base cochés (E-mail, AS400, Internet). @return list<string> */
    public function baseAccessLabels(): array
    {
        return array_values(array_filter([
            $this->needs_email ? __('E-mail') : null,
            $this->needs_as400 ? 'AS400' : null,
            $this->needs_internet ? __('Internet') : null,
        ]));
    }
}
