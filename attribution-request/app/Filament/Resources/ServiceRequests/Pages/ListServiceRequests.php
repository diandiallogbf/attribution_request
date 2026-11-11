<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    public function getTabs(): array
    {
        $tab = fn (string $label, array $statuses): Tab => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses))
            ->badge(fn () => ServiceRequest::query()->whereIn('status', $statuses)->count() ?: null);

        return [
            'dsi' => $tab('À approuver', [RequestStatus::PendingDsi])->badgeColor('warning'),
            'fulfilment' => $tab('À traiter', [RequestStatus::Approved, RequestStatus::Ordering, RequestStatus::ReadyForAssignment, RequestStatus::PendingReceipt]),
            'manager' => $tab('Chez le manager', [RequestStatus::PendingManager, RequestStatus::Returned]),
            'done' => $tab('Terminées', [RequestStatus::Closed, RequestStatus::Rejected, RequestStatus::Cancelled]),
            'all' => Tab::make('Toutes'),
        ];
    }
}
