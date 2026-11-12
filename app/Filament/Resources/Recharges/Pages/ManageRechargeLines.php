<?php

namespace App\Filament\Resources\Recharges\Pages;

use App\Filament\Resources\Recharges\RechargeLineResource;
use App\Services\Finance\FinanceExports;
use App\Support\Spreadsheet\XlsxWorkbook;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageRechargeLines extends ManageRecords
{
    protected static string $resource = RechargeLineResource::class;

    public function getSubheading(): string
    {
        return 'Lignes actives de la flotte et montant rechargé chaque mois. Relevé exportable pour la comptabilité.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exporter (Excel)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (FinanceExports $exports): StreamedResponse {
                    $month = ucfirst(now()->translatedFormat('F Y'));

                    return $exports
                        ->recharges(XlsxWorkbook::make(), $this->getFilteredSortedTableQuery()->with(FinanceExports::LINE_RELATIONS)->lazy(200), 'Recharges '.$month)
                        ->download(FinanceExports::fileName('recharges-'.now()->format('Y-m')));
                }),
        ];
    }
}
