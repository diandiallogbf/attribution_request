<?php

namespace App\Filament\Resources\RechargeAmounts;

use App\Enums\Role;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\RechargeAmounts\Pages\ManageRechargeAmounts;
use App\Models\RechargeAmount;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class RechargeAmountResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = RechargeAmount::class;

    protected static ?string $slug = 'referentiels/montants-de-recharge';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels';

    protected static ?string $navigationLabel = 'Montants de recharge';

    protected static ?string $modelLabel = 'montant';

    protected static ?int $navigationSort = 3;

    protected static function allowedRoles(): array
    {
        return [Role::Admin, Role::Dsi];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('amount_gnf')->label('Montant (GNF)')->numeric()->minValue(1000)->required()->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Proposé sur la fiche')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('amount_gnf')
            ->columns([
                TextColumn::make('amount_gnf')->label('Montant mensuel')->formatStateUsing(fn ($state) => $state?->format()),
                ToggleColumn::make('is_active')->label('Proposé'),
            ])
            ->recordActions([EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRechargeAmounts::route('/')];
    }
}
