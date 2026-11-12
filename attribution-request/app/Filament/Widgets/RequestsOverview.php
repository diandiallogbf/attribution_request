<?php

namespace App\Filament\Widgets;

use App\Enums\DeviceStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\Device;
use App\Models\ServiceRequest;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RequestsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    /** La Finance n'a que faire du flux des demandes : elle voit ses propres indicateurs. */
    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyRole([Role::Admin, Role::Dsi, Role::ItManager, Role::ItAgent, Role::Assistant]);
    }

    protected function getStats(): array
    {
        $pendingDsi = ServiceRequest::query()->where('status', RequestStatus::PendingDsi)->count();
        $late = ServiceRequest::query()
            ->whereIn('status', [RequestStatus::PendingManager, RequestStatus::PendingDsi])
            ->where('submitted_at', '<', now()->subDays(2))
            ->count();
        $toFulfil = ServiceRequest::query()
            ->whereIn('status', [RequestStatus::Approved, RequestStatus::Ordering, RequestStatus::ReadyForAssignment, RequestStatus::PendingReceipt])
            ->count();
        $inStock = Device::query()->where('status', DeviceStatus::InStock)->count();

        $closedThisMonth = ServiceRequest::query()
            ->where('status', RequestStatus::Closed)
            ->where('closed_at', '>=', now()->startOfMonth())
            ->count();

        return [
            Stat::make('À approuver', $pendingDsi)
                ->description($late > 0 ? "{$late} en attente depuis plus de 48 h" : 'Aucun retard')
                ->descriptionIcon($late > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($late > 0 ? 'warning' : 'success')
                ->url(ServiceRequestResource::getUrl('index', ['tab' => 'dsi'])),
            Stat::make('À traiter (commande / remise)', $toFulfil)
                ->description('Approuvées, en commande ou à remettre')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color('primary')
                ->url(ServiceRequestResource::getUrl('index', ['tab' => 'fulfilment'])),
            Stat::make('Appareils en stock', $inStock)
                ->description($inStock < 3 ? 'Stock bas : prévoir un bon de commande' : 'Stock disponible')
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color($inStock < 3 ? 'danger' : 'gray'),
            Stat::make('Clôturées ce mois', $closedThisMonth)
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon(Heroicon::OutlinedArchiveBox)
                ->color('success'),
        ];
    }
}
