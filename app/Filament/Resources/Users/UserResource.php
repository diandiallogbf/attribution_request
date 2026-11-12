<?php

namespace App\Filament\Resources\Users;

use App\Enums\EmployeeCategory;
use App\Enums\Role;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Department;
use App\Models\Site;
use App\Models\User;
use App\Rules\ValidValueObject;
use App\ValueObjects\Matricule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use UnitEnum;

/**
 * Comptes et organigramme (en attendant la synchronisation Microsoft Entra ID).
 * Le N+1 et le service déterminent qui valide les demandes.
 */
class UserResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'organisation/utilisateurs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'last_name';

    protected static function allowedRoles(): array
    {
        return [Role::Admin, Role::Dsi];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Identité')->columns(2)->columnSpanFull()->schema([
                TextInput::make('first_name')->label('Prénom')->required()->maxLength(60),
                TextInput::make('last_name')->label('Nom')->required()->maxLength(60),
                TextInput::make('nickname')->label('Surnom')->maxLength(60),
                TextInput::make('matricule')->label('Matricule')->maxLength(4)->unique(ignoreRecord: true)
                    ->rules([new ValidValueObject(Matricule::class)]),
                TextInput::make('email')->label('E-mail professionnel')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('job_title')->label('Fonction')->maxLength(100),
            ]),
            Section::make('Organisation')->columns(2)->columnSpanFull()->schema([
                Select::make('category')->label('Catégorie')->options(EmployeeCategory::class),
                Select::make('site_id')->label('Site')->options(fn () => Site::query()->pluck('name', 'id')->all()),
                Select::make('department_id')->label('Service')->searchable()
                    ->options(fn () => Department::query()->with('direction')->get()->mapWithKeys(fn (Department $d) => [$d->id => $d->direction->code.' — '.$d->name])->all()),
                Select::make('manager_id')->label('Responsable (N+1)')->searchable()
                    ->options(fn (?User $record) => User::query()->active()->when($record, fn ($q) => $q->whereKeyNot($record->id))->orderBy('last_name')->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name])->all())
                    ->helperText('Valide les demandes de ce collaborateur.'),
            ]),
            Section::make('Accès au portail')->columns(2)->columnSpanFull()->schema([
                Select::make('roles')->label('Rôles')->multiple()->preload()
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => Role::tryFrom($record->name)?->getLabel() ?? $record->name)
                    ->helperText('Sans rôle : collaborateur (portail uniquement).'),
                Toggle::make('is_active')->label('Compte actif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['department.direction', 'manager', 'roles']))
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')->label('Nom')
                    ->formatStateUsing(fn (User $record): string => $record->name)
                    ->description(fn (User $record): ?string => $record->job_title)
                    ->searchable(['first_name', 'last_name', 'email', 'matricule'])
                    ->sortable(),
                TextColumn::make('matricule')->label('Matricule')->placeholder('—')->toggleable(),
                TextColumn::make('department.name')->label('Service')
                    ->description(fn (User $record): ?string => $record->department?->direction?->code)
                    ->placeholder('—'),
                TextColumn::make('manager.name')->label('N+1')->placeholder('—'),
                TextColumn::make('roles.name')->label('Rôles')->badge()
                    ->formatStateUsing(fn (string $state): string => Role::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('last_login_at')->label('Dernière connexion')->since()->placeholder('Jamais')->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')->label('Actif'),
            ])
            ->filters([
                SelectFilter::make('department_id')->label('Service')->relationship('department', 'name'),
                SelectFilter::make('roles')->label('Rôle')->relationship('roles', 'name'),
                TernaryFilter::make('is_active')->label('Actif'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('sendPasswordLink')
                    ->label('Lien de mot de passe')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record): string => "Un e-mail permettant de choisir un mot de passe sera envoyé à {$record->email}.")
                    ->action(function (User $record): void {
                        $status = Password::sendResetLink(['email' => $record->email]);

                        Notification::make()
                            ->title($status === Password::RESET_LINK_SENT ? 'Lien envoyé' : 'Envoi impossible')
                            ->body(__($status))
                            ->{$status === Password::RESET_LINK_SENT ? 'success' : 'danger'}()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
