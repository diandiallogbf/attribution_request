<?php

namespace App\Livewire\Requests;

use App\Actions\Requests\SaveProfileDraft;
use App\Actions\Requests\StartProfileDraft;
use App\Actions\Requests\SubmitServiceRequest;
use App\Enums\ApprovalDecision;
use App\Enums\ProfileAction;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Exceptions\Domain\MissingApproverException;
use App\Livewire\Concerns\HandlesDomainExceptions;
use App\Livewire\Forms\Profile\ProfileForm;
use App\Models\Application;
use App\Models\Direction;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use App\Models\Site;
use App\Models\User;
use App\Services\ApproverResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Assistant de la fiche profil utilisateur (AGL-GIN-DSI-F-0008).
 * Les étapes s'adaptent à l'action : une suppression n'a besoin que du collaborateur et de la date de départ.
 */
#[Title('Fiche profil utilisateur')]
class ProfileWizard extends Component
{
    use HandlesDomainExceptions;

    #[Locked]
    public ?int $requestId = null;

    #[Locked]
    public bool $isResubmission = false;

    public string $step = 'action';

    public ProfileForm $profile;

    public ?string $savedAt = null;

    public function mount(?ServiceRequest $serviceRequest = null): void
    {
        if ($serviceRequest?->exists) {
            abort_unless($serviceRequest->type === RequestType::UserProfile, 404);
            $this->authorize('update', $serviceRequest);

            $this->requestId = $serviceRequest->id;
            $this->isResubmission = $serviceRequest->status === RequestStatus::Returned;
            $this->profile->fillFrom($serviceRequest);
            $this->step = $this->isResubmission ? 'review' : 'person';

            return;
        }

        $this->profile->managerId = auth()->id();
        $this->profile->departmentId = auth()->user()->department_id;
        $this->profile->siteId = auth()->user()->site_id;
    }

    public function updated(string $property): void
    {
        $this->resetValidation($property);

        // Changer d'action efface le choix de la personne (création ↔ collaborateur existant).
        if ($property === 'profile.action' && $this->requestId === null) {
            $this->profile->beneficiaryId = $this->profile->actionEnum()?->concernsNewcomer() ? null : auth()->id();
        }
    }

    // ── Navigation ───────────────────────────────────────────────────────────

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->persist();
        $this->step = $this->neighbour(+1);
    }

    public function previous(): void
    {
        $this->resetValidation();
        $this->step = $this->neighbour(-1);
    }

    public function goTo(string $step): void
    {
        $keys = array_keys($this->steps);
        $target = array_search($step, $keys, true);
        $current = array_search($this->step, $keys, true);

        if ($target !== false && ($target < $current || $this->step === 'review')) {
            $this->resetValidation();
            $this->step = $step;
        }
    }

    public function submit(): void
    {
        foreach (array_keys($this->steps) as $step) {
            try {
                $this->validateStep($step);
            } catch (ValidationException $exception) {
                $this->step = $step;

                throw $exception;
            }
        }

        $this->persist();

        $request = app(SubmitServiceRequest::class)->handle($this->serviceRequest(), auth()->user(), true, $this->profile->comment ?: null);

        $request->load('approvals.assignedTo');
        $pending = $request->approvals->filter(fn (RequestApproval $approval) => $approval->decision === ApprovalDecision::Pending);

        session()->flash('submitted', match (true) {
            $pending->count() > 1 => __('Votre demande :reference a été transmise aux :count directeurs concernés pour validation métier.', ['reference' => $request->displayReference(), 'count' => $pending->count()]),
            $pending->first()?->assignedTo !== null => __('Votre demande :reference a été transmise à :name pour validation.', ['reference' => $request->displayReference(), 'name' => $pending->first()->assignedTo->name]),
            default => __('Votre demande :reference a été transmise à la DSI pour validation.', ['reference' => $request->displayReference()]),
        });

        $this->redirectRoute('requests.show', $request, navigate: true);
    }

    // ── Étapes ───────────────────────────────────────────────────────────────

    /** @return array<string, array{label: string, description: string, icon: string}> */
    #[Computed]
    public function steps(): array
    {
        $steps = [
            'action' => ['label' => __('Type de demande'), 'description' => __('Création, modification, suppression'), 'icon' => 'clipboard-document-list'],
            'person' => ['label' => __('Collaborateur'), 'description' => __('Identité et rattachement'), 'icon' => 'user'],
        ];

        if ($this->profile->actionEnum()?->requiresAccessDetails() ?? true) {
            $steps['access'] = ['label' => __('Accès'), 'description' => __('E-mail, AS400, Internet'), 'icon' => 'key'];
            $steps['applications'] = ['label' => __('Applications'), 'description' => __('Outils métier'), 'icon' => 'squares-2x2'];
            $steps['equipment'] = ['label' => __('Matériel'), 'description' => __('Poste, imprimante, GSM'), 'icon' => 'computer-desktop'];
        }

        $steps['review'] = ['label' => __('Récapitulatif'), 'description' => __('Circuit de validation'), 'icon' => 'clipboard-document-check'];

        return $steps;
    }

    private function neighbour(int $offset): string
    {
        $keys = array_keys($this->steps);
        $index = array_search($this->step, $keys, true);

        return $keys[max(0, min(count($keys) - 1, $index + $offset))];
    }

    private function validateStep(string $step): void
    {
        if ($step === 'action' && $this->requestId !== null) {
            return; // l'action est figée dès la création du brouillon
        }

        $this->validate($this->profile->rulesFor($step), $this->profile->messagesFor());

        if ($step === 'person' && ! $this->profile->actionEnum()?->concernsNewcomer() && $this->requestId === null) {
            $beneficiary = User::query()->findOrFail($this->profile->beneficiaryId);

            if (! app(ApproverResolver::class)->canRequestFor(auth()->user(), $beneficiary)) {
                throw ValidationException::withMessages([
                    'profile.beneficiaryId' => __('Vous pouvez faire une demande pour vous-même ou pour un membre de votre équipe uniquement.'),
                ]);
            }
        }
    }

    private function persist(): void
    {
        if ($this->step === 'action') {
            return; // le brouillon est créé quand la personne concernée est connue
        }

        $user = auth()->user();

        if ($this->requestId === null) {
            $action = $this->profile->actionEnum() ?? ProfileAction::Modification;
            $beneficiary = $action->concernsNewcomer() ? null : User::query()->find($this->profile->beneficiaryId);
            $this->requestId = app(StartProfileDraft::class)->handle($user, $action, $beneficiary)->id;
        }

        app(SaveProfileDraft::class)->handle($this->serviceRequest(), $user, $this->profile->toData());
        $this->savedAt = now()->format('H:i');
    }

    public function serviceRequest(): ?ServiceRequest
    {
        return $this->requestId ? ServiceRequest::query()->with('profileDetail')->find($this->requestId) : null;
    }

    // ── Données d'affichage ──────────────────────────────────────────────────

    #[Computed]
    public function canCreateForNewcomers(): bool
    {
        return StartProfileDraft::canRequestForNewcomers(auth()->user());
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function teamMembers(): Collection
    {
        return app(ApproverResolver::class)->teamOf(auth()->user());
    }

    #[Computed]
    public function beneficiaryUser(): ?User
    {
        return $this->profile->beneficiaryId
            ? User::query()->with(['department.direction', 'site', 'manager'])->find($this->profile->beneficiaryId)
            : null;
    }

    /** @return Collection<int, Direction> */
    #[Computed]
    public function directions(): Collection
    {
        return Direction::query()->with('departments')->orderBy('name')->get();
    }

    /** @return Collection<int, Site> */
    #[Computed]
    public function sites(): Collection
    {
        return Site::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** Responsables possibles (N+1) pour un nouvel arrivant. @return Collection<int, User> */
    #[Computed]
    public function managers(): Collection
    {
        return User::query()->active()->orderBy('last_name')->get();
    }

    /** @return Collection<int, Application> */
    #[Computed]
    public function applications(): Collection
    {
        return Application::query()->active()->with('ownerDirection.director')->get();
    }

    /** Circuit de validation prévu, affiché au récapitulatif. @return array{manager: ?User, directions: Collection<int, Direction>} */
    #[Computed]
    public function circuit(): array
    {
        $manager = null;

        try {
            $manager = $this->beneficiaryUser
                ? app(ApproverResolver::class)->managerFor($this->beneficiaryUser)
                : User::query()->find($this->profile->managerId);
        } catch (MissingApproverException) {
        }

        $selected = collect($this->profile->applicationIds)->map(fn ($id) => (int) $id);

        $directions = $this->applications
            ->filter(fn (Application $application) => $selected->contains($application->id) && $application->needsBusinessApproval())
            ->map(fn (Application $application) => $application->ownerDirection)
            ->unique('id')
            ->values();

        return ['manager' => $manager, 'directions' => $directions];
    }

    public function returnComment(): ?string
    {
        if (! $this->isResubmission) {
            return null;
        }

        return RequestApproval::query()
            ->where('service_request_id', $this->requestId)
            ->where('decision', ApprovalDecision::Returned)
            ->latest('decided_at')
            ->value('comment');
    }

    public function render(): View
    {
        return view('livewire.requests.profile-wizard', [
            'returnComment' => $this->returnComment(),
        ])->title($this->isResubmission ? __('Corriger la fiche profil') : __('Fiche profil utilisateur'));
    }
}
