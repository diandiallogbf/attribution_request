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

/** Ventilation analytique : qui consomme quoi (direction du bénéficiaire). */
class SpendingByDirectionTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

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
            ->heading('Par direction')
            ->description('Matériel de l\'exercice '.$year.' (direction du bénéficiaire à la demande) et recharges des lignes actives.')
            ->records(fn (): array => collect(app(SpendingReport::class)->byDirection($year))
                ->mapWithKeys(fn (array $row): array => [$row['code'] => [
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'equipment' => $row['equipment']->amount,
                    'lines' => $row['lines'],
                    'monthly' => $row['monthlyRecharge']->amount,
                    'yearly' => $row['monthlyRecharge']->times(12)->amount,
                    'total' => $row['equipment']->amount + $row['monthlyRecharge']->times(12)->amount,
                ]])
                ->all())
            ->columns([
                TextColumn::make('name')->label('Direction')->weight(FontWeight::Medium)
                    ->description(fn (array $record): ?string => $record['code'] === SpendingReport::UNALLOCATED ? 'Réapprovisionnement, matériel sans bénéficiaire' : $record['code']),
                TextColumn::make('equipment')->label('Matériel '.$year)->alignEnd()->formatStateUsing($money),
                TextColumn::make('lines')->label('Lignes')->alignEnd()->formatStateUsing(fn (int $state): string => $state ?: '—'),
                TextColumn::make('monthly')->label('Recharges / mois')->alignEnd()->formatStateUsing($money),
                TextColumn::make('yearly')->label('Recharges / an')->alignEnd()->formatStateUsing($money)->color('gray'),
                TextColumn::make('total')->label('Coût annuel estimé')->alignEnd()->formatStateUsing($money)->weight(FontWeight::SemiBold),
            ])
            ->emptyStateHeading('Aucune dépense sur cet exercice')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->paginated(false);
    }
}
