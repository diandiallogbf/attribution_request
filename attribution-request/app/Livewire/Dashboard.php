<?php

namespace App\Livewire;

use App\Enums\RequestStatus;
use App\Models\Assignment;
use App\Models\Device;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tableau de bord')]
class Dashboard extends Component
{
    #[Computed]
    public function openCount(): int
    {
        return ServiceRequest::query()->involving(auth()->user())->open()->where('status', '!=', RequestStatus::Draft)->count();
    }

    #[Computed]
    public function draftCount(): int
    {
        return ServiceRequest::query()->where('requester_id', auth()->id())->where('status', RequestStatus::Draft)->count();
    }

    #[Computed]
    public function pendingApprovals(): int
    {
        return RequestApproval::query()->awaiting(auth()->user())->count();
    }

    /** @return Collection<int, ServiceRequest> */
    #[Computed]
    public function recentRequests(): Collection
    {
        return ServiceRequest::query()
            ->involving(auth()->user())
            ->with(['beneficiary', 'phoneDetail', 'simDetail', 'profileDetail'])
            ->latest('updated_at')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, Assignment> */
    #[Computed]
    public function equipment(): Collection
    {
        return Assignment::query()
            ->active()
            ->where('user_id', auth()->id())
            ->with('assignable')
            ->latest('assigned_at')
            ->get()
            ->loadMorph('assignable', [Device::class => ['model']]);
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
