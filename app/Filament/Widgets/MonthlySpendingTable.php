<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Services\Finance\SpendingReport;
use App\ValueObjects\MoneyGnf;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** Synthèse mensuelle des commandes : engagé (BC), facturé, réglé, dépense retenue. */
class MonthlySpendingTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(Role::financeReaders());
    }

    public function table(Table $table): Table
    {
        $year = (int) ($this->pageFilters['year'] ?? now()->year);
        $money = fn (?int $state): string => $state ? MoneyGnf::from($state)->format() : '—';

        return $table
            ->heading('Dépenses par mois — '.$year)
            ->description('Date de commande. La facture fait foi dès qu\'elle est saisie ; à défaut, le montant du bon de commande.')
            ->records(function () use ($year): array {
                $months = app(SpendingReport::class)->monthly($year);
                $records = [];

                foreach ($months as $month) {
                    $records['m'.$month['month']] = [
                        'label' => $month['label'],
                        'orders' => $month['orders'],
                        'ordered' => $month['ordered']->amount,
                        'invoiced' => $month['invoiced']->amount,
                        'paid' => $month['paid']->amount,
                        'spent' => $month['spent']->amount,
                        'is_total' => false,
                    ];
                }

                $records['total'] = [
                    'label' => 'Total '.$year,
                    'orders' => array_sum(array_column($months, 'orders')),
                    'ordered' => MoneyGnf::sum(array_column($months, 'ordered'))->amount,
                    'invoiced' => MoneyGnf::sum(array_column($months, 'invoiced'))->amount,
                    'paid' => MoneyGnf::sum(array_column($months, 'paid'))->amount,
                    'spent' => MoneyGnf::sum(array_column($months, 'spent'))->amount,
                    'is_total' => true,
                ];

                return $records;
            })
            ->columns([
                TextColumn::make('label')->label('Mois')
                    ->weight(fn (array $record): ?FontWeight => $record['is_total'] ? FontWeight::Bold : null),
                TextColumn::make('orders')->label('Commandes')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => $state ?: '—'),
                TextColumn::make('ordered')->label('Bons de commande')->alignEnd()->formatStateUsing($money),
                TextColumn::make('invoiced')->label('Facturé')->alignEnd()->formatStateUsing($money),
                TextColumn::make('paid')->label('Réglé')->alignEnd()->formatStateUsing($money)
                    ->color(fn (int $state): ?string => $state > 0 ? 'success' : null),
                TextColumn::make('spent')->label('Dépense retenue')->alignEnd()->formatStateUsing($money)
                    ->weight(FontWeight::SemiBold),
            ])
            ->paginated(false);
    }
}
