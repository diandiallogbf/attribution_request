<?php

namespace App\Filament\Resources\DeviceModels;

use App\Enums\PhoneType;
use App\Enums\Role;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\DeviceModels\Pages\ManageDeviceModels;
use App\Models\DeviceModel;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DeviceModelResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = DeviceModel::class;

    protected static ?string $slug = 'referentiels/modeles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels';

    protected static ?string $navigationLabel = 'Catalogue des modèles';

    protected static ?string $modelLabel = 'modèle';

    protected static ?int $navigationSort = 1;

    protected static function allowedRoles(): array
    {
        return [Role::Admin, Role::Dsi, Role::ItManager];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('brand')->label('Marque')->required()->maxLength(60),
            TextInput::make('name')->label('Modèle')->required()->maxLength(100),
            Select::make('phone_type')->label('Type')->options(PhoneType::class)->required(),
            TextInput::make('storage_gb')->label('Capacité (Go)')->numeric()->minValue(1),
            TextInput::make('unit_price_gnf')->label('Prix unitaire (GNF)')->numeric()->minValue(0),
            Toggle::make('is_active')->label('Proposé à la commande')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('brand')
            ->columns([
                TextColumn::make('label')->label('Modèle')->searchable(['brand', 'name']),
                TextColumn::make('phone_type')->label('Type')->badge()->color('gray'),
                TextColumn::make('unit_price_gnf')->label('Prix')->formatStateUsing(fn ($state) => $state?->format())->placeholder('—')->alignEnd(),
                TextColumn::make('devices_count')->label('Parc')->counts('devices')->alignEnd(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeviceModels::route('/')];
    }
}
