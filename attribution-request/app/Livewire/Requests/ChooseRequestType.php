<?php

namespace App\Livewire\Requests;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Nouvelle demande')]
class ChooseRequestType extends Component
{
    /** Brouillons à reprendre plutôt que d'en recommencer un. @return Collection<int, ServiceRequest> */
    #[Computed]
    public function drafts(): Collection
    {
        return ServiceRequest::query()
            ->where('requester_id', auth()->id())
            ->where('status', RequestStatus::Draft)
            ->with(['beneficiary', 'phoneDetail', 'simDetail', 'profileDetail'])
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.requests.choose-request-type');
    }
}
