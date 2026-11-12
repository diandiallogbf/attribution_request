<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Recharges\RechargeLineResource;
use App\Services\Finance\SpendingReport;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Indicateurs Finance : dépenses de l'exercice, factures attendues / à régler, recharges engagées. */
class FinanceStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(Role::financeReaders());
    }

    protected function getHeading(): ?string
    {
        // Sur l'espace Finance, le titre de page dit déjà « Dépenses d'attribution ».
        return $this->pageFilters === null
            ? 'Dépenses d\'attribution — exercice '.$this->year()
            : 'Exercice '.$this->year();
    }

    protected function getStats(): array
    {
        $totals = app(SpendingReport::class)->totals($this->year());

        return [
            Stat::make('Matériel et puces commandés', $totals['spent']->format())
                ->description($totals['orders'].' commande(s) · '.$totals['spentThisMonth']->format().' ce mois-ci')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('primary')
                ->url(OrderResource::getUrl('index')),
            Stat::make('En attente de facture', $totals['toInvoice']['amount']->format())
                ->description($totals['toInvoice']['count'].' commande(s) sans facture saisie')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color($totals['toInvoice']['count'] > 0 ? 'gray' : 'success'),
            Stat::make('Factures à régler', $totals['unpaid']['amount']->format())
                ->description($totals['unpaid']['count'].' facture(s) non réglée(s)')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($totals['unpaid']['count'] > 0 ? 'warning' : 'success'),
            Stat::make('Recharges mensuelles', $totals['monthlyRecharge']->format())
                ->description($totals['lines'].' ligne(s) active(s) · '.$totals['monthlyRecharge']->times(12)->format().' / an')
                ->descriptionIcon(Heroicon::OutlinedSignal)
                ->color('violet')
                ->url(RechargeLineResource::getUrl('index')),
        ];
    }

    private function year(): int
    {
        return (int) ($this->pageFilters['year'] ?? now()->year);
    }
}
