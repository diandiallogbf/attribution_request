<?php

namespace App\Data;

use App\Enums\EmployeeCategory;
use App\Enums\EquipmentType;
use App\Enums\ProfileAction;
use App\Enums\ProfileType;
use App\ValueObjects\Matricule;
use App\ValueObjects\PhoneNumber;
use Carbon\CarbonInterface;

/**
 * Contenu typé d'une fiche profil utilisateur, construit par l'assistant et consommé par les Actions.
 */
final readonly class ProfileRequestData
{
    /**
     * @param  list<int>  $applicationIds
     * @param  list<EquipmentType>  $equipment
     */
    public function __construct(
        public ProfileAction $action,
        // Nouveau collaborateur (création)
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?Matricule $matricule = null,
        public ?string $jobTitle = null,
        public ?EmployeeCategory $category = null,
        public ?int $departmentId = null,
        public ?int $siteId = null,
        public ?int $managerId = null,
        public ?ProfileType $profileType = null,
        public ?CarbonInterface $internshipEndsAt = null,
        public ?CarbonInterface $effectiveAt = null,
        // Accès
        public ?string $referenceProfile = null,
        public bool $needsEmail = false,
        public ?string $distributionLists = null,
        public bool $needsAs400 = false,
        public bool $needsInternet = false,
        public array $applicationIds = [],
        public ?string $otherApplication = null,
        // Matériels
        public array $equipment = [],
        public ?PhoneNumber $gsmNumber = null,
        public ?string $comment = null,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        $newcomer = $this->action->concernsNewcomer();
        $details = $this->action->requiresAccessDetails();

        return [
            'action' => $this->action,
            'first_name' => $newcomer ? $this->firstName : null,
            'last_name' => $newcomer ? $this->lastName : null,
            'matricule' => $newcomer ? $this->matricule : null,
            'job_title' => $newcomer ? $this->jobTitle : null,
            'category' => $newcomer ? $this->category : null,
            'department_id' => $newcomer ? $this->departmentId : null,
            'site_id' => $newcomer ? $this->siteId : null,
            'manager_id' => $newcomer ? $this->managerId : null,
            'profile_type' => $newcomer ? $this->profileType : null,
            'internship_ends_at' => $newcomer && $this->profileType === ProfileType::Internship ? $this->internshipEndsAt : null,
            'effective_at' => $this->effectiveAt,
            'reference_profile' => $details ? $this->referenceProfile : null,
            'needs_email' => $details && $this->needsEmail,
            'distribution_lists' => $details && $this->needsEmail ? $this->distributionLists : null,
            'needs_as400' => $details && $this->needsAs400,
            'needs_internet' => $details && $this->needsInternet,
            'other_application' => $details ? $this->otherApplication : null,
            'equipment' => $details ? $this->equipment : [],
            'gsm_number' => $details ? $this->gsmNumber : null,
            'comment' => $this->comment,
        ];
    }
}
