<?php

namespace App\Filament\Resources\Directions;

use App\Enums\Role;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Directions\Pages\ManageDirections;
use App\Models\Direction;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DirectionResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = Direction::class;

    protected static ?string $slug = 'organisation/directions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?string $navigationLabel = 'Directions';

    protected static ?string $modelLabel = 'direction';

    protected static ?int $navigationSort = 2;

    protected static function allowedRoles(): array
    {
        return [Role::Admin, Role::Dsi];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Code')->required()->maxLength(10)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nom')->required()->maxLength(120),
            Select::make('director_id')->label('Directeur')->searchable()->columnSpanFull()
                ->options(fn () => User::query()->active()->orderBy('last_name')->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name])->all())
                ->helperText('Valide les accès aux applications dont la direction est propriétaire.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Code')->badge()->color('gray'),
                TextColumn::make('name')->label('Direction')->searchable(),
                TextColumn::make('director.name')->label('Directeur')->placeholder('Non défini')
                    ->color(fn (Direction $record): ?string => $record->director_id ? null : 'danger'),
                TextColumn::make('departments_count')->label('Services')->counts('departments')->alignEnd(),
            ])
            ->recordActions([EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDirections::route('/')];
    }
}
