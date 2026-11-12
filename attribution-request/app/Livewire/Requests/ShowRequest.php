<?php

namespace App\Livewire\Requests;

use App\Actions\Fulfilment\ConfirmReceipt;
use App\Actions\Requests\CancelServiceRequest;
use App\Actions\Requests\DecideApproval;
use App\Enums\ApprovalDecision;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Livewire\Concerns\HandlesDomainExceptions;
use App\Models\Assignment;
use App\Models\Device;
use App\Models\ServiceRequest;
use App\Models\SimLine;
use App\Services\AllowanceCheck;
use App\Services\AllowanceChecker;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ShowRequest extends Component
{
    use HandlesDomainExceptions;

    #[Locked]
    public int $requestId;

    public ?string $pendingDecision = null;

    public string $decisionComment = '';

    public string $cancelReason = '';

    public bool $receiptPolicyAccepted = false;

    public function mount(ServiceRequest $serviceRequest): void
    {
        $serviceRequest->load(['beneficiary', 'approvals']);
        $this->authorize('view', $serviceRequest);
        $this->requestId = $serviceRequest->id;
    }

    #[Computed]
    public function serviceRequest(): ServiceRequest
    {
        $request = ServiceRequest::query()
            ->with([
                'beneficiary.department.direction', 'beneficiary.site', 'requester', 'executedBy',
                'phoneDetail.previousDevice.model', 'phoneDetail.approvedDeviceModel',
                'simDetail.existingSimLine',
                'profileDetail.applications.ownerDirection', 'profileDetail.department', 'profileDetail.site', 'profileDetail.manager',
                'approvals.assignedTo', 'approvals.decidedBy', 'approvals.direction',
                'events.actor', 'archivedForms', 'assignments.assignable',
            ])
            ->findOrFail($this->requestId);

        $request->assignments->loadMorph('assignable', [Device::class => ['model'], SimLine::class => []]);

        return $request;
    }

    #[Computed]
    public function allowanceCheck(): ?AllowanceCheck
    {
        $request = $this->serviceRequest;

        if ($request->type !== RequestType::Telephony || $request->beneficiary === null) {
            return null;
        }

        $phone = $request->phoneDetail;

        return app(AllowanceChecker::class)->check(
            $request->beneficiary,
            $phone?->reason?->requiresNewDevice() ? $phone->requested_phone_type : null,
            $request->simDetail?->requested_monthly_recharge_gnf,
            isNewDevice: (bool) $phone?->reason?->requiresNewDevice(),
        );
    }

    /** Matériel remis dans le cadre de cette demande (à confirmer par le bénéficiaire). @return Collection<int, Assignment> */
    #[Computed]
    public function handedOver(): Collection
    {
        return $this->serviceRequest->assignments;
    }

    public function canConfirmReceipt(): bool
    {
        $request = $this->serviceRequest;

        return $request->status === RequestStatus::PendingReceipt && $request->beneficiary_id === auth()->id();
    }

    public function openDecision(string $decision): void
    {
        $this->pendingDecision = ApprovalDecision::from($decision)->value;
        $this->decisionComment = '';
        $this->resetValidation();
        Flux::modal('decision')->show();
    }

    public function decide(): void
    {
        $decision = ApprovalDecision::from((string) $this->pendingDecision);
        $this->authorize('decide', $this->serviceRequest);

        $this->validate(
            ['decisionComment' => [$decision->requiresComment() ? 'required' : 'nullable', 'string', 'max:1000']],
            ['decisionComment.required' => __('Un commentaire est obligatoire pour expliquer votre décision au demandeur.')],
        );

        app(DecideApproval::class)->handle($this->serviceRequest, auth()->user(), $decision, $this->decisionComment ?: null);

        Flux::modal('decision')->close();

        Flux::toast(
            text: match ($decision) {
                ApprovalDecision::Approved => __('La demande poursuit son circuit. Le demandeur est informé.'),
                ApprovalDecision::Returned => __('Le demandeur va corriger sa demande.'),
                default => __('Le demandeur est informé du rejet.'),
            },
            heading: $decision->getLabel(),
            variant: $decision === ApprovalDecision::Approved ? 'success' : 'warning',
        );

        // Navigation SPA : rafraîchit aussi les compteurs de la barre latérale (le toast est persisté).
        $this->redirectRoute('requests.show', $this->serviceRequest, navigate: true);
    }

    public function confirmReceipt(): void
    {
        $this->validate(
            ['receiptPolicyAccepted' => ['accepted']],
            ['receiptPolicyAccepted.accepted' => __('Cochez l\'engagement pour confirmer la réception.')],
        );

        app(ConfirmReceipt::class)->handle($this->serviceRequest, auth()->user(), $this->receiptPolicyAccepted);

        Flux::toast(text: __('Merci ! La demande est clôturée et la fiche archivée.'), heading: __('Réception confirmée'), variant: 'success');

        $this->redirectRoute('requests.show', $this->serviceRequest, navigate: true);
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->serviceRequest);

        app(CancelServiceRequest::class)->handle($this->serviceRequest, auth()->user(), $this->cancelReason ?: null);

        Flux::modal('cancel')->close();
        Flux::toast(text: __('La demande a été annulée.'), variant: 'success');

        $this->redirectRoute('requests.show', $this->serviceRequest, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.requests.show-request')
            ->title($this->serviceRequest->displayReference());
    }
}
