<?php

namespace App\Livewire\Requests;

use App\Actions\Requests\SaveTelephonyDraft;
use App\Actions\Requests\StartTelephonyDraft;
use App\Actions\Requests\SubmitServiceRequest;
use App\Data\TelephonyRequestData;
use App\Enums\ApprovalDecision;
use App\Enums\LineOption;
use App\Enums\PhoneReason;
use App\Enums\PhoneType;
use App\Enums\PlanType;
use App\Enums\RequestStatus;
use App\Enums\SimAction;
use App\Exceptions\Domain\MissingApproverException;
use App\Livewire\Concerns\HandlesDomainExceptions;
use App\Livewire\Forms\Telephony\BeneficiaryForm;
use App\Livewire\Forms\Telephony\PhoneForm;
use App\Livewire\Forms\Telephony\SimForm;
use App\Livewire\Forms\Telephony\SubjectForm;
use App\Models\Assignment;
use App\Models\Device;
use App\Models\RechargeAmount;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use App\Models\SimLine;
use App\Models\User;
use App\Services\AllowanceCheck;
use App\Services\AllowanceChecker;
use App\Services\ApproverResolver;
use App\ValueObjects\MoneyGnf;
use App\ValueObjects\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Assistant de demande Téléphonie : 5 étapes courtes, brouillon enregistré à chaque étape.
 * Les étapes « Téléphone » et « Ligne SIM » n'apparaissent que si elles sont demandées.
 */
#[Title('Nouvelle demande')]
class TelephonyWizard extends Component
{
    use HandlesDomainExceptions;

    #[Locked]
    public ?int $requestId = null;

    #[Locked]
    public bool $isResubmission = false;

    public string $step = 'beneficiary';

    public BeneficiaryForm $beneficiary;

    public SubjectForm $subject;

    public PhoneForm $phone;

    public SimForm $sim;

    public bool $policyAccepted = false;

    public string $comment = '';

    public ?string $savedAt = null;

    /** Pré-remplissage depuis « Mes équipements » (ex. ?motif=replacement). */
    #[Url(as: 'motif', history: false, keep: false)]
    public ?string $prefillReason = null;

    public function mount(?ServiceRequest $serviceRequest = null): void
    {
        if ($serviceRequest?->exists) {
            $this->authorize('update', $serviceRequest);
            $this->requestId = $serviceRequest->id;
            $this->isResubmission = $serviceRequest->status === RequestStatus::Returned;
            $this->fillFromRequest($serviceRequest);
            $this->step = $this->isResubmission ? 'review' : 'subject';

            return;
        }

        $this->beneficiary->beneficiaryId = auth()->id();

        if ($reason = PhoneReason::tryFrom((string) $this->prefillReason)) {
            $this->subject->wantsPhone = true;
            $this->phone->reason = $reason->value;
        }
    }

    /** Dès que l'utilisateur corrige un champ, son message d'erreur disparaît. */
    public function updated(string $property): void
    {
        $this->resetValidation($property);

        if (str_starts_with($property, 'subject.')) {
            $this->resetValidation('subject.wantsPhone');
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

        // On ne saute en avant que depuis le récapitulatif (toutes les étapes sont alors remplies).
        if ($target !== false && ($target < $current || $this->step === 'review')) {
            $this->resetValidation();
            $this->step = $step;
        }
    }

    public function submit(): void
    {
        $this->validate(
            ['policyAccepted' => ['accepted'], 'comment' => ['nullable', 'string', 'max:1000']],
            ['policyAccepted.accepted' => __('Cochez l\'engagement pour envoyer la demande.')],
        );

        // Revalidation complète : le récapitulatif ne doit jamais envoyer une étape incomplète.
        foreach (array_keys($this->steps) as $step) {
            try {
                $this->validateStep($step);
            } catch (ValidationException $exception) {
                $this->step = $step;

                throw $exception;
            }
        }

        $this->persist();

        $request = app(SubmitServiceRequest::class)->handle(
            $this->serviceRequest(),
            auth()->user(),
            $this->policyAccepted,
            $this->comment ?: null,
        );

        $request->load('approvals.assignedTo');
        $pending = $request->approvals->first(fn (RequestApproval $approval) => $approval->decision === ApprovalDecision::Pending);

        session()->flash('submitted', $pending?->assignedTo
            ? __('Votre demande :reference a été transmise à :name pour validation.', ['reference' => $request->displayReference(), 'name' => $pending->assignedTo->name])
            : __('Votre demande :reference a été transmise à la DSI pour approbation.', ['reference' => $request->displayReference()]));

        $this->redirectRoute('requests.show', $request, navigate: true);
    }

    // ── Étapes ───────────────────────────────────────────────────────────────

    /** @return array<string, array{label: string, description: string, icon: string}> */
    #[Computed]
    public function steps(): array
    {
        $steps = [
            'beneficiary' => ['label' => __('Bénéficiaire'), 'description' => __('Pour qui est la demande'), 'icon' => 'user'],
            'subject' => ['label' => __('Objet'), 'description' => __('Téléphone et/ou ligne'), 'icon' => 'squares-2x2'],
        ];

        if ($this->subject->wantsPhone) {
            $steps['phone'] = ['label' => __('Téléphone'), 'description' => __('Motif et type d\'appareil'), 'icon' => 'device-phone-mobile'];
        }

        if ($this->subject->wantsSim) {
            $steps['sim'] = ['label' => __('Ligne SIM'), 'description' => __('Action, forfait, recharge'), 'icon' => 'signal'];
        }

        $steps['review'] = ['label' => __('Récapitulatif'), 'description' => __('Vérifier et envoyer'), 'icon' => 'clipboard-document-check'];

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
        match ($step) {
            'beneficiary' => $this->validateBeneficiary(),
            'subject' => $this->subject->validate(),
            'phone' => $this->validatePhone(),
            'sim' => $this->validateSim(),
            default => null,
        };
    }

    private function validateBeneficiary(): void
    {
        if ($this->requestId !== null) {
            return; // bénéficiaire figé dès la création du brouillon
        }

        $this->beneficiary->validate();

        $beneficiary = User::query()->findOrFail($this->beneficiary->beneficiaryId);

        if (! app(ApproverResolver::class)->canRequestFor(auth()->user(), $beneficiary)) {
            throw ValidationException::withMessages([
                'beneficiary.beneficiaryId' => __('Vous pouvez faire une demande pour vous-même ou pour un membre de votre équipe uniquement.'),
            ]);
        }
    }

    private function validatePhone(): void
    {
        $this->phone->validate();

        $choice = $this->phone->previousDeviceChoice;
        if ($this->phone->reasonEnum()?->requiresPreviousDevice() && ctype_digit((string) $choice)
            && ! $this->beneficiaryDevices->contains(fn (Assignment $assignment) => $assignment->assignable_id === (int) $choice)) {
            throw ValidationException::withMessages(['phone.previousDeviceChoice' => __('Cet appareil n\'est pas attribué au bénéficiaire.')]);
        }
    }

    private function validateSim(): void
    {
        $this->sim->validate();

        $choice = $this->sim->existingLineChoice;
        if ($this->sim->actionEnum()?->requiresExistingLine() && ctype_digit((string) $choice)
            && ! $this->beneficiaryLines->contains(fn (Assignment $assignment) => $assignment->assignable_id === (int) $choice)) {
            throw ValidationException::withMessages(['sim.existingLineChoice' => __('Cette ligne n\'est pas attribuée au bénéficiaire.')]);
        }
    }

    private function persist(): void
    {
        $user = auth()->user();

        if ($this->requestId === null) {
            $beneficiary = User::query()->findOrFail($this->beneficiary->beneficiaryId);
            $this->requestId = app(StartTelephonyDraft::class)->handle($user, $beneficiary)->id;
        } elseif ($this->step !== 'beneficiary') {
            app(SaveTelephonyDraft::class)->handle($this->serviceRequest(), $user, $this->toData());
        }

        $this->savedAt = now()->format('H:i');
    }

    private function toData(): TelephonyRequestData
    {
        $previous = (string) $this->phone->previousDeviceChoice;
        $line = (string) $this->sim->existingLineChoice;

        return new TelephonyRequestData(
            wantsPhone: $this->subject->wantsPhone,
            wantsSim: $this->subject->wantsSim,
            phoneReason: PhoneReason::tryFrom((string) $this->phone->reason),
            phoneType: PhoneType::tryFrom((string) $this->phone->phoneType),
            otherTypeLabel: $this->phone->otherTypeLabel ?: null,
            detailedReason: $this->phone->detailedReason ?: null,
            previousDeviceId: ctype_digit($previous) ? (int) $previous : null,
            previousDeviceLabel: $previous === 'other' ? ($this->phone->previousDeviceLabel ?: null) : null,
            previousDeviceSerial: $previous === 'other' ? ($this->phone->previousDeviceSerial ?: null) : null,
            simAction: SimAction::tryFrom((string) $this->sim->action),
            planType: PlanType::tryFrom((string) $this->sim->planType),
            lineOptions: array_values(array_filter(array_map(fn (string $option) => LineOption::tryFrom($option), $this->sim->options))),
            existingSimLineId: ctype_digit($line) ? (int) $line : null,
            existingMsisdn: $line === 'other' ? PhoneNumber::tryFrom($this->sim->existingMsisdn) : null,
            entity: $this->sim->entity ?: null,
            monthlyRecharge: ctype_digit((string) $this->sim->monthlyRecharge) ? MoneyGnf::from((int) $this->sim->monthlyRecharge) : null,
        );
    }

    private function fillFromRequest(ServiceRequest $request): void
    {
        $request->loadMissing(['phoneDetail', 'simDetail']);

        $this->beneficiary->beneficiaryId = $request->beneficiary_id;
        $this->subject->wantsPhone = $request->phoneDetail !== null;
        $this->subject->wantsSim = $request->simDetail !== null;
        $this->comment = (string) $request->comment;

        if ($phone = $request->phoneDetail) {
            $this->phone->reason = $phone->reason?->value;
            $this->phone->phoneType = $phone->requested_phone_type?->value;
            $this->phone->otherTypeLabel = (string) $phone->other_type_label;
            $this->phone->detailedReason = (string) $phone->detailed_reason;
            $this->phone->previousDeviceChoice = $phone->previous_device_id ? (string) $phone->previous_device_id : ($phone->previous_device_label ? 'other' : null);
            $this->phone->previousDeviceLabel = (string) $phone->previous_device_label;
            $this->phone->previousDeviceSerial = (string) $phone->previous_device_serial;
        }

        if ($sim = $request->simDetail) {
            $this->sim->action = $sim->action?->value;
            $this->sim->existingLineChoice = $sim->existing_sim_line_id ? (string) $sim->existing_sim_line_id : ($sim->existing_msisdn ? 'other' : null);
            $this->sim->existingMsisdn = (string) $sim->existing_msisdn?->national();
            $this->sim->planType = $sim->plan_type?->value;
            $this->sim->options = $sim->options?->map(fn (LineOption $option) => $option->value)->values()->all() ?? [];
            $this->sim->monthlyRecharge = $sim->requested_monthly_recharge_gnf ? (string) $sim->requested_monthly_recharge_gnf->amount : null;
            $this->sim->entity = (string) $sim->entity;
        }
    }

    // ── Données d'affichage ──────────────────────────────────────────────────

    public function serviceRequest(): ?ServiceRequest
    {
        return $this->requestId ? ServiceRequest::query()->with(['phoneDetail', 'simDetail'])->find($this->requestId) : null;
    }

    #[Computed]
    public function beneficiaryUser(): ?User
    {
        return $this->beneficiary->beneficiaryId
            ? User::query()->with(['department.direction', 'site', 'manager'])->find($this->beneficiary->beneficiaryId)
            : null;
    }

    /** Collaborateurs pour lesquels l'utilisateur connecté peut faire une demande. @return Collection<int, User> */
    #[Computed]
    public function teamMembers(): Collection
    {
        $user = auth()->user();

        return User::query()
            ->active()
            ->whereKeyNot($user->id)
            ->when($user->manager_id, fn ($query, int $managerId) => $query->whereKeyNot($managerId))
            ->where(fn ($query) => $query
                ->where('manager_id', $user->id)
                ->orWhereIn('department_id', $user->getConnection()->table('departments')->where('head_id', $user->id)->select('id')))
            ->orderBy('last_name')
            ->get();
    }

    /** @return Collection<int, Assignment> */
    #[Computed]
    public function beneficiaryDevices(): Collection
    {
        return Assignment::query()
            ->active()
            ->where('user_id', $this->beneficiary->beneficiaryId)
            ->where('assignable_type', (new Device)->getMorphClass())
            ->with('assignable.model')
            ->latest('assigned_at')
            ->get();
    }

    /** @return Collection<int, Assignment> */
    #[Computed]
    public function beneficiaryLines(): Collection
    {
        return Assignment::query()
            ->active()
            ->where('user_id', $this->beneficiary->beneficiaryId)
            ->where('assignable_type', (new SimLine)->getMorphClass())
            ->with('assignable')
            ->latest('assigned_at')
            ->get();
    }

    /** @return Collection<int, PhoneType> */
    #[Computed]
    public function allowedPhoneTypes(): Collection
    {
        return $this->beneficiaryUser ? app(AllowanceChecker::class)->allowedPhoneTypes($this->beneficiaryUser) : collect();
    }

    #[Computed]
    public function maxRecharge(): ?MoneyGnf
    {
        return $this->beneficiaryUser ? app(AllowanceChecker::class)->maxMonthlyRecharge($this->beneficiaryUser) : null;
    }

    /** @return Collection<int, RechargeAmount> */
    #[Computed]
    public function rechargeAmounts(): Collection
    {
        return RechargeAmount::query()->active()->get();
    }

    #[Computed]
    public function allowanceCheck(): ?AllowanceCheck
    {
        if (! $this->beneficiaryUser) {
            return null;
        }

        $data = $this->toData();

        return app(AllowanceChecker::class)->check(
            $this->beneficiaryUser,
            $data->wantsPhone && $data->phoneReason?->requiresNewDevice() ? $data->phoneType : null,
            $data->wantsSim ? $data->monthlyRecharge : null,
            isNewDevice: $data->wantsPhone && (bool) $data->phoneReason?->requiresNewDevice(),
        );
    }

    /** Valideur de l'étape 2 (ou null si c'est le demandeur lui-même : validation d'office). */
    #[Computed]
    public function approver(): ?User
    {
        if (! $this->beneficiaryUser) {
            return null;
        }

        try {
            return app(ApproverResolver::class)->managerFor($this->beneficiaryUser);
        } catch (MissingApproverException) {
            return null;
        }
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
        return view('livewire.requests.telephony-wizard', [
            'returnComment' => $this->returnComment(),
        ])->title($this->isResubmission ? __('Corriger la demande') : __('Nouvelle demande'));
    }
}
