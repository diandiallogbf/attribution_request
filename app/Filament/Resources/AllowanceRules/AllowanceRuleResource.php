<?php

namespace App\Filament\Resources\AllowanceRules;

use App\Enums\EmployeeCategory;
use App\Enums\PhoneType;
use App\Enums\Role;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\AllowanceRules\Pages\ManageAllowanceRules;
use App\Models\AllowanceRule;
use App\Models\RechargeAmount;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Grille de dotation : ce à quoi chaque catégorie de collaborateur a droit. */
class AllowanceRuleResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = AllowanceRule::class;

    protected static ?string $slug = 'referentiels/grille-de-dotation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels';

    protected static ?string $navigationLabel = 'Grille de dotation';

    protected static ?string $modelLabel = 'règle de dotation';

    protected static ?int $navigationSort = 2;

    protected static function allowedRoles(): array
    {
        return [Role::Admin, Role::Dsi];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('employee_category')->label('Catégorie')->options(EmployeeCategory::class)->required()->unique(ignoreRecord: true),
            TextInput::make('renewal_months')->label('Renouvellement (mois)')->numeric()->minValue(1)->maxValue(120)->required(),
            CheckboxList::make('allowed_phone_types')->label('Types de téléphone autorisés')
                ->options(collect(PhoneType::cases())->reject(fn (PhoneType $type) => $type === PhoneType::Other)->mapWithKeys(fn (PhoneType $type) => [$type->value => $type->getLabel()])->all())
                ->columns(2)->required()->columnSpanFull(),
            Select::make('max_monthly_recharge_gnf')->label('Plafond de recharge mensuelle')->required()
                ->options(fn () => RechargeAmount::query()->active()->get()->mapWithKeys(fn (RechargeAmount $amount) => [$amount->amount_gnf->amount => $amount->amount_gnf->format()])->all()),
            Textarea::make('notes')->label('Notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_category')->label('Catégorie')->badge(),
                TextColumn::make('allowed_phone_types')->label('Téléphones')->badge()->color('gray'),
                TextColumn::make('max_monthly_recharge_gnf')->label('Plafond / mois')->formatStateUsing(fn ($state) => $state?->format())->alignEnd(),
                TextColumn::make('renewal_months')->label('Renouvellement')->suffix(' mois')->alignEnd(),
                TextColumn::make('notes')->label('Notes')->limit(40)->color('gray')->toggleable(),
            ])
            ->recordActions([EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAllowanceRules::route('/')];
    }
}
