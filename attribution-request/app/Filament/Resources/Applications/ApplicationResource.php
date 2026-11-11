<?php

namespace App\Filament\Resources\Applications;

use App\Enums\Role;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Applications\Pages\ManageApplications;
use App\Models\Application;
use App\Models\Direction;
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

/** Applications métier de la fiche profil et direction qui en valide l'accès. */
class ApplicationResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = Application::class;

    protected static ?string $slug = 'referentiels/applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels';

    protected static ?string $navigationLabel = 'Applications métier';

    protected static ?string $modelLabel = 'application';

    protected static ?int $navigationSort = 4;

    protected static function allowedRoles(): array
    {
        return [Role::Admin, Role::Dsi];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Code')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nom')->required()->maxLength(80),
            TextInput::make('description')->label('Description')->maxLength(160)->columnSpanFull(),
            Select::make('owner_direction_id')->label('Direction propriétaire')
                ->options(fn () => Direction::query()->orderBy('code')->get()->mapWithKeys(fn (Direction $d) => [$d->id => $d->code.' — '.$d->name])->all())
                ->helperText('Son directeur valide les demandes d\'accès (validation métier).'),
            Toggle::make('requires_business_approval')->label('Validation métier requise')->default(true),
            TextInput::make('sort_order')->label('Ordre sur la fiche')->numeric()->default(0),
            Toggle::make('is_active')->label('Proposée sur la fiche')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label('Application')->description(fn (Application $record): ?string => $record->description)->searchable(),
                TextColumn::make('ownerDirection.code')->label('Validée par')->badge()->color('warning')->placeholder('—'),
                IconColumn::make('requires_business_approval')->label('Validation métier')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageApplications::route('/')];
    }
}
