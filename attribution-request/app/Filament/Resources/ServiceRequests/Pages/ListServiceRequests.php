<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Enums\RequestStatus;
use App\Enums\Role;
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

        $tabs = [
            'dsi' => $tab('À approuver', [RequestStatus::PendingDsi])->badgeColor('warning'),
            'fulfilment' => $tab('À traiter', RequestStatus::fulfilment()),
            'validation' => $tab('En validation', [RequestStatus::PendingManager, RequestStatus::PendingBusiness, RequestStatus::Returned]),
            'done' => $tab('Terminées', [RequestStatus::Closed, RequestStatus::Rejected, RequestStatus::Cancelled]),
            'all' => Tab::make('Toutes'),
        ];

        if (! $this->isDelegate()) {
            return $tabs;
        }

        // L'équipe IT ouvre d'abord ce que la DSI lui a confié.
        return [
            'mine' => Tab::make('Mes tâches')
                ->icon('heroicon-m-user-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->handledBy(auth()->user()))
                ->badge(fn () => ServiceRequest::query()->handledBy(auth()->user())->count() ?: null)
                ->badgeColor('violet'),
            ...$tabs,
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return $this->isDelegate() ? 'mine' : 'dsi';
    }

    private function isDelegate(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(Role::delegates());
    }
}
