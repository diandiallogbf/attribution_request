<?php

namespace App\Livewire\Approvals;

use App\Models\RequestApproval;
use App\Services\AllowanceChecker;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('À valider')]
class ApprovalInbox extends Component
{
    /** @return Collection<int, RequestApproval> */
    #[Computed]
    public function approvals(): Collection
    {
        return RequestApproval::query()
            ->awaiting(auth()->user())
            ->with([
                'serviceRequest.beneficiary.department',
                'serviceRequest.requester',
                'serviceRequest.phoneDetail',
                'serviceRequest.simDetail',
                'serviceRequest.profileDetail',
                'direction',
            ])
            ->oldest()
            ->get()
            // Pas d'auto-validation : on masque ses propres demandes.
            ->reject(fn (RequestApproval $approval) => $approval->serviceRequest->beneficiary_id === auth()->id());
    }

    /** Écarts à la grille de dotation, calculés pour chaque demande listée. @return array<int, bool> */
    #[Computed]
    public function compliance(): array
    {
        $checker = app(AllowanceChecker::class);

        return $this->approvals->mapWithKeys(function (RequestApproval $approval) use ($checker) {
            $request = $approval->serviceRequest;
            $phone = $request->phoneDetail;

            if ($request->beneficiary === null) {
                return [$approval->id => true]; // fiche profil d'un nouvel arrivant : pas de grille de dotation
            }

            return [$approval->id => $checker->check(
                $request->beneficiary,
                $phone?->reason?->requiresNewDevice() ? $phone->requested_phone_type : null,
                $request->simDetail?->requested_monthly_recharge_gnf,
            )->isCompliant()];
        })->all();
    }

    public function render(): View
    {
        return view('livewire.approvals.approval-inbox');
    }
}
