<?php

namespace App\Livewire\Requests;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Mes demandes')]
class MyRequests extends Component
{
    use WithPagination;

    #[Url(as: 'onglet', except: 'open')]
    public string $tab = 'open';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** @return array<string, array{label: string, statuses: list<RequestStatus>|null}> */
    public function tabs(): array
    {
        $closed = [RequestStatus::Closed, RequestStatus::Rejected, RequestStatus::Cancelled];

        return [
            'open' => ['label' => __('En cours'), 'statuses' => array_values(array_filter(RequestStatus::open(), fn ($s) => $s !== RequestStatus::Draft))],
            'drafts' => ['label' => __('Brouillons'), 'statuses' => [RequestStatus::Draft]],
            'closed' => ['label' => __('Terminées'), 'statuses' => $closed],
            'all' => ['label' => __('Toutes'), 'statuses' => null],
        ];
    }

    /** @return Builder<ServiceRequest> */
    private function baseQuery(): Builder
    {
        return ServiceRequest::query()->involving(auth()->user());
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        $byStatus = $this->baseQuery()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect($this->tabs())->map(fn (array $tab) => $tab['statuses'] === null
            ? (int) $byStatus->sum()
            : (int) collect($tab['statuses'])->sum(fn (RequestStatus $status) => $byStatus[$status->value] ?? 0))->all();
    }

    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        $statuses = $this->tabs()[$this->tab]['statuses'] ?? null;

        return $this->baseQuery()
            ->with(['beneficiary', 'phoneDetail', 'simDetail', 'profileDetail'])
            ->when($statuses, fn (Builder $query) => $query->whereIn('status', $statuses))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where('reference', 'like', '%'.preg_replace('/\s+/', '', $this->search).'%'))
            ->latest('updated_at')
            ->paginate(10);
    }

    public function render(): View
    {
        return view('livewire.requests.my-requests');
    }
}
