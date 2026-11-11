<?php

namespace App\Filament\Resources\Departments;

use App\Enums\Role;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Models\Department;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DepartmentResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = Department::class;

    protected static ?string $slug = 'organisation/services';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?string $navigationLabel = 'Services';

    protected static ?string $modelLabel = 'service';

    protected static ?int $navigationSort = 3;

    protected static function allowedRoles(): array
    {
        return [Role::Admin, Role::Dsi];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('direction_id')->label('Direction')->required()
                ->options(fn () => Direction::query()->orderBy('code')->get()->mapWithKeys(fn (Direction $d) => [$d->id => $d->code.' -'.$d->name])->all()),
            TextInput::make('name')->label('Service')->required()->maxLength(120),
            Select::make('head_id')->label('Chef de service')->searchable()->columnSpanFull()
                ->options(fn () => User::query()->active()->orderBy('last_name')->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name])->all())
                ->helperText('Valide à défaut de N+1 et peut faire des demandes pour les membres du service.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['direction', 'head']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('direction.code')->label('Direction')->badge()->color('gray'),
                TextColumn::make('name')->label('Service')->searchable(),
                TextColumn::make('head.name')->label('Chef de service')->placeholder('—'),
                TextColumn::make('users_count')->label('Collaborateurs')->counts('users')->alignEnd(),
            ])
            ->filters([SelectFilter::make('direction_id')->label('Direction')->relationship('direction', 'code')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDepartments::route('/')];
    }
}
