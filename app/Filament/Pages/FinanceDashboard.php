<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Filament\Widgets\FinanceStats;
use App\Filament\Widgets\MonthlySpendingTable;
use App\Filament\Widgets\SpendingByDirectionTable;
use App\Models\Order;
use App\Services\Finance\FinanceExports;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Espace Finance : ce que coûtent les attributions (matériel, puces, recharges),
 * par mois et par direction, avec export Excel pour la comptabilité.
 */
class FinanceDashboard extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/finance';

    protected static ?string $title = 'Dépenses d\'attribution';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(Role::financeReaders());
    }

    public function getSubheading(): string
    {
        return 'Matériel et puces commandés, factures, règlements et recharges mensuelles de la flotte.';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('year')
                ->label('Exercice')
                ->options(fn (): array => $this->years())
                ->default(now()->year)
                ->selectablePlaceholder(false),
        ]);
    }

    public function getWidgets(): array
    {
        return [FinanceStats::class, MonthlySpendingTable::class, SpendingByDirectionTable::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exporter (Excel)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (FinanceExports $exports): StreamedResponse {
                    $year = $this->year();

                    return $exports->yearWorkbook($year)->download(FinanceExports::fileName("depenses-{$year}"));
                }),
        ];
    }

    public function year(): int
    {
        return (int) ($this->filters['year'] ?? now()->year);
    }

    /** @return array<int, string> */
    private function years(): array
    {
        $first = Order::query()->min('ordered_at');
        $from = min(now()->year, $first ? (int) substr((string) $first, 0, 4) : now()->year);

        return collect(range(now()->year, $from))->mapWithKeys(fn (int $year) => [$year => (string) $year])->all();
    }
}
