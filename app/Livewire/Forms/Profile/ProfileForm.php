<?php

namespace App\Livewire\Forms\Profile;

use App\Data\ProfileRequestData;
use App\Enums\EmployeeCategory;
use App\Enums\EquipmentType;
use App\Enums\ProfileAction;
use App\Enums\ProfileType;
use App\Models\ProfileRequestDetail;
use App\Models\ServiceRequest;
use App\Rules\ValidValueObject;
use App\ValueObjects\Matricule;
use App\ValueObjects\PhoneNumber;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Formulaire de la fiche profil utilisateur. Les règles sont regroupées par étape de l'assistant
 * (rulesFor) pour valider uniquement ce que l'utilisateur vient de saisir.
 */
class ProfileForm extends Form
{
    public ?string $action = null;

    /** Modification / suppression : collaborateur existant. */
    public ?int $beneficiaryId = null;

    // Création : nouveau collaborateur
    public string $firstName = '';

    public string $lastName = '';

    public string $matricule = '';

    public string $jobTitle = '';

    public ?string $category = null;

    public ?int $departmentId = null;

    public ?int $siteId = null;

    public ?int $managerId = null;

    public ?string $profileType = null;

    public ?string $internshipEndsAt = null;

    public ?string $effectiveAt = null;

    // Accès
    public string $referenceProfile = '';

    public bool $needsEmail = false;

    public string $distributionLists = '';

    public bool $needsAs400 = false;

    public bool $needsInternet = false;

    /** @var list<int|string> */
    public array $applicationIds = [];

    public string $otherApplication = '';

    // Matériels
    /** @var list<string> */
    public array $equipment = [];

    public string $gsmNumber = '';

    public string $comment = '';

    public function actionEnum(): ?ProfileAction
    {
        return ProfileAction::tryFrom((string) $this->action);
    }

    /**
     * Règles d'une étape, préfixées pour Livewire (« profile.firstName »).
     *
     * @return array<string, mixed>
     */
    public function rulesFor(string $step): array
    {
        $action = $this->actionEnum();

        $rules = match ($step) {
            'action' => [
                'action' => ['required', Rule::enum(ProfileAction::class)],
            ],
            'person' => $action?->concernsNewcomer() ? [
                'firstName' => ['required', 'string', 'max:60'],
                'lastName' => ['required', 'string', 'max:60'],
                'matricule' => ['nullable', 'string', new ValidValueObject(Matricule::class), Rule::unique('users', 'matricule')],
                'jobTitle' => ['required', 'string', 'max:100'],
                'category' => ['required', Rule::enum(EmployeeCategory::class)],
                'departmentId' => ['required', 'integer', Rule::exists('departments', 'id')],
                'siteId' => ['required', 'integer', Rule::exists('sites', 'id')],
                'managerId' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
                'profileType' => ['required', Rule::enum(ProfileType::class)],
                'internshipEndsAt' => [Rule::requiredIf($this->profileType === ProfileType::Internship->value), 'nullable', 'date', 'after:today'],
                'effectiveAt' => ['nullable', 'date'],
            ] : [
                'beneficiaryId' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
                'effectiveAt' => [Rule::requiredIf($action === ProfileAction::Deletion), 'nullable', 'date'],
            ],
            'access' => [
                'referenceProfile' => ['nullable', 'string', 'max:120'],
                'needsEmail' => ['boolean'],
                'distributionLists' => ['nullable', 'string', 'max:500'],
                'needsAs400' => ['boolean'],
                'needsInternet' => ['boolean'],
            ],
            'applications' => [
                'applicationIds' => ['array'],
                'applicationIds.*' => ['integer', Rule::exists('applications', 'id')->where('is_active', true)],
                'otherApplication' => ['nullable', 'string', 'max:120'],
            ],
            'equipment' => [
                'equipment' => ['array'],
                'equipment.*' => [Rule::enum(EquipmentType::class)],
                'gsmNumber' => ['nullable', 'string', new ValidValueObject(PhoneNumber::class)],
            ],
            'review' => [
                'comment' => ['nullable', 'string', 'max:1000'],
                'needsEmail' => [function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->actionEnum()?->requiresAccessDetails() && ! $this->requestsSomething()) {
                        $fail(__('La fiche est vide : cochez au moins un accès, une application ou un matériel.'));
                    }
                }],
            ],
            default => [],
        };

        return collect($rules)->mapWithKeys(fn ($rule, string $key) => ['profile.'.$key => $rule])->all();
    }

    /** @return array<string, string> */
    public function messagesFor(): array
    {
        return [
            'profile.action.required' => __('Choisissez le type de demande.'),
            'profile.firstName.required' => __('Le prénom est obligatoire.'),
            'profile.lastName.required' => __('Le nom est obligatoire.'),
            'profile.matricule.unique' => __('Ce matricule est déjà attribué à un collaborateur du portail.'),
            'profile.jobTitle.required' => __('La fonction est obligatoire.'),
            'profile.category.required' => __('Choisissez la catégorie.'),
            'profile.departmentId.required' => __('Choisissez le service.'),
            'profile.siteId.required' => __('Choisissez le site.'),
            'profile.managerId.required' => __('Indiquez le responsable (N+1) qui validera la demande.'),
            'profile.profileType.required' => __('Indiquez s\'il s\'agit d\'un profil permanent ou d\'un stage.'),
            'profile.internshipEndsAt.required' => __('La date de fin de stage est obligatoire.'),
            'profile.internshipEndsAt.after' => __('La date de fin de stage doit être dans le futur.'),
            'profile.beneficiaryId.required' => __('Choisissez le collaborateur concerné.'),
            'profile.effectiveAt.required' => __('Indiquez la date de départ : les accès seront fermés à cette date.'),
        ];
    }

    public function requestsSomething(): bool
    {
        return $this->needsEmail || $this->needsAs400 || $this->needsInternet
            || $this->applicationIds !== [] || trim($this->otherApplication) !== ''
            || $this->equipment !== [] || trim($this->gsmNumber) !== '';
    }

    public function toData(): ProfileRequestData
    {
        return new ProfileRequestData(
            action: $this->actionEnum() ?? ProfileAction::Modification,
            firstName: trim($this->firstName) ?: null,
            lastName: trim($this->lastName) ?: null,
            matricule: Matricule::tryFrom($this->matricule),
            jobTitle: trim($this->jobTitle) ?: null,
            category: EmployeeCategory::tryFrom((string) $this->category),
            departmentId: $this->departmentId,
            siteId: $this->siteId,
            managerId: $this->managerId,
            profileType: ProfileType::tryFrom((string) $this->profileType),
            internshipEndsAt: $this->internshipEndsAt ? Date::parse($this->internshipEndsAt) : null,
            effectiveAt: $this->effectiveAt ? Date::parse($this->effectiveAt) : null,
            referenceProfile: trim($this->referenceProfile) ?: null,
            needsEmail: $this->needsEmail,
            distributionLists: trim($this->distributionLists) ?: null,
            needsAs400: $this->needsAs400,
            needsInternet: $this->needsInternet,
            applicationIds: array_map('intval', $this->applicationIds),
            otherApplication: trim($this->otherApplication) ?: null,
            equipment: array_values(array_filter(array_map(fn (string $value) => EquipmentType::tryFrom($value), $this->equipment))),
            gsmNumber: PhoneNumber::tryFrom($this->gsmNumber),
            comment: trim($this->comment) ?: null,
        );
    }

    public function fillFrom(ServiceRequest $request): void
    {
        /** @var ProfileRequestDetail $detail */
        $detail = $request->profileDetail()->with('applications')->firstOrFail();

        $this->action = $detail->action?->value;
        $this->beneficiaryId = $request->beneficiary_id;
        $this->firstName = (string) $detail->first_name;
        $this->lastName = (string) $detail->last_name;
        $this->matricule = (string) $detail->matricule?->toStorage();
        $this->jobTitle = (string) $detail->job_title;
        $this->category = $detail->category?->value;
        $this->departmentId = $detail->department_id;
        $this->siteId = $detail->site_id;
        $this->managerId = $detail->manager_id;
        $this->profileType = $detail->profile_type?->value;
        $this->internshipEndsAt = $detail->internship_ends_at?->toDateString();
        $this->effectiveAt = $detail->effective_at?->toDateString();
        $this->referenceProfile = (string) $detail->reference_profile;
        $this->needsEmail = $detail->needs_email;
        $this->distributionLists = (string) $detail->distribution_lists;
        $this->needsAs400 = $detail->needs_as400;
        $this->needsInternet = $detail->needs_internet;
        $this->applicationIds = $detail->applications->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->otherApplication = (string) $detail->other_application;
        $this->equipment = $detail->equipment?->map(fn (EquipmentType $type) => $type->value)->values()->all() ?? [];
        $this->gsmNumber = (string) $detail->gsm_number?->national();
        $this->comment = (string) $detail->comment;
    }
}
