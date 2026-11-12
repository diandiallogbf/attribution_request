<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\Finance\FinanceExports;
use App\Support\Spreadsheet\XlsxWorkbook;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageOrders extends ManageRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exporter (Excel)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->tooltip('Exporte les commandes affichées (filtres et recherche appliqués).')
                ->action(fn (FinanceExports $exports): StreamedResponse => $exports
                    ->orders(XlsxWorkbook::make(), $this->getFilteredSortedTableQuery()->with(FinanceExports::ORDER_RELATIONS)->lazy(200))
                    ->download(FinanceExports::fileName('commandes'))),
        ];
    }
}
