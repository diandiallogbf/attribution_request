<?php

namespace App\Filament\Resources\Recharges;

use App\Enums\PlanType;
use App\Enums\Role;
use App\Enums\SimLineStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Recharges\Pages\ManageRechargeLines;
use App\Models\Direction;
use App\Models\SimLine;
use App\ValueObjects\MoneyGnf;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Relevé des recharges mensuelles de la flotte pour la Finance (lecture seule) :
 * ligne, titulaire, direction, montant mensuel. La gestion des lignes reste dans « Parc ».
 */
class RechargeLineResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = SimLine::class;

    protected static ?string $slug = 'recharges';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Recharges mensuelles';

    protected static ?string $modelLabel = 'ligne';

    protected static ?string $pluralModelLabel = 'recharges mensuelles';

    protected static ?int $navigationSort = 2;

    protected static function allowedRoles(): array
    {
        return Role::financeReaders();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', SimLineStatus::Active)
            ->where('is_fleet', true)
            ->with(['operator', 'currentAssignment.user.department.direction']);
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state): string => $state instanceof MoneyGnf ? $state->format() : '—';

        return $table
            ->defaultSort('monthly_recharge_gnf', 'desc')
            ->columns([
                TextColumn::make('msisdn')->label('Numéro')->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn ($state) => $state?->international())
                    ->searchable(),
                TextColumn::make('holder')->label('Titulaire')
                    ->state(fn (SimLine $record): string => $record->currentAssignment?->user?->name ?? 'Non affectée')
                    ->description(fn (SimLine $record): ?string => $record->currentAssignment?->user?->department?->name),
                TextColumn::make('direction')->label('Direction')->badge()->color('gray')
                    ->state(fn (SimLine $record): string => $record->currentAssignment?->user?->department?->direction?->code ?? '—'),
                TextColumn::make('plan_type')->label('Forfait')->badge(),
                TextColumn::make('monthly_recharge_gnf')->label('Recharge mensuelle')->alignEnd()->sortable()
                    ->formatStateUsing($money)
                    ->summarize(Sum::make()->label('Total mensuel')->formatStateUsing(fn ($state): string => MoneyGnf::from((int) $state)->format())),
                TextColumn::make('yearly')->label('Projection annuelle')->alignEnd()->color('gray')
                    ->state(fn (SimLine $record): string => ($record->monthly_recharge_gnf ?? MoneyGnf::zero())->times(12)->format()),
                TextColumn::make('activated_at')->label('Active depuis')->date('d/m/Y')->placeholder('—')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Direction')
                    ->options(fn (): array => Direction::query()->orderBy('code')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $direction) => $query->whereHas('assignments', fn (Builder $assignment) => $assignment
                            ->whereNull('returned_at')
                            ->whereHas('user.department', fn (Builder $department) => $department->where('direction_id', $direction))),
                    )),
                SelectFilter::make('plan_type')->label('Forfait')->options(PlanType::class),
            ])
            ->emptyStateHeading('Aucune ligne active')
            ->emptyStateDescription('Les lignes de la flotte attribuées apparaîtront ici avec leur recharge mensuelle.')
            ->emptyStateIcon(Heroicon::OutlinedSignal);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRechargeLines::route('/'),
        ];
    }
}
