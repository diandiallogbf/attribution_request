<?php

namespace App\Filament\Resources\SimLines;

use App\Enums\LineOption;
use App\Enums\PlanType;
use App\Enums\Role;
use App\Enums\SimLineStatus;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\SimLines\Pages\ManageSimLines;
use App\Models\Operator;
use App\Models\RechargeAmount;
use App\Models\SimLine;
use App\Rules\UniqueValueObject;
use App\Rules\ValidValueObject;
use App\ValueObjects\Iccid;
use App\ValueObjects\PhoneNumber;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SimLineResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = SimLine::class;

    protected static ?string $slug = 'parc/lignes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Parc';

    protected static ?string $navigationLabel = 'Lignes SIM';

    protected static ?string $modelLabel = 'ligne';

    protected static ?string $pluralModelLabel = 'lignes';

    protected static ?int $navigationSort = 2;

    protected static function allowedRoles(): array
    {
        return Role::fulfilment();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('msisdn')->label('Numéro')->required()->placeholder('622 12 34 56')
                ->rules(fn (?SimLine $record) => [new ValidValueObject(PhoneNumber::class), new UniqueValueObject(PhoneNumber::class, SimLine::class, 'msisdn', $record, 'Ce numéro est déjà dans le parc.')]),
            TextInput::make('iccid')->label('ICCID')->helperText('Numéro imprimé sur la carte SIM')
                ->rules(fn (?SimLine $record) => [new ValidValueObject(Iccid::class), new UniqueValueObject(Iccid::class, SimLine::class, 'iccid', $record, 'Cette puce est déjà enregistrée.')]),
            Select::make('operator_id')->label('Opérateur')->required()
                ->options(fn () => Operator::query()->pluck('name', 'id')->all())
                ->default(fn () => Operator::query()->value('id')),
            Select::make('status')->label('Statut')->options(SimLineStatus::class)->default(SimLineStatus::InStock->value)->required(),
            Select::make('plan_type')->label('Forfait')->options(PlanType::class)->default(PlanType::Prepaid->value)->required(),
            Select::make('monthly_recharge_gnf')->label('Recharge mensuelle')
                ->options(fn () => RechargeAmount::query()->active()->get()->mapWithKeys(fn (RechargeAmount $amount) => [$amount->amount_gnf->amount => $amount->amount_gnf->format()])->all()),
            CheckboxList::make('options')->label('Options')->options(LineOption::class)->columns(3)->columnSpanFull(),
            Toggle::make('is_fleet')->label('Ligne de flotte')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['operator', 'currentAssignment.user']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('msisdn')->label('Numéro')->fontFamily(FontFamily::Mono)->formatStateUsing(fn ($state) => $state?->international())->searchable()->copyable(),
                TextColumn::make('status')->label('Statut')->badge()->sortable(),
                TextColumn::make('plan_type')->label('Forfait'),
                TextColumn::make('monthly_recharge_gnf')->label('Recharge / mois')->formatStateUsing(fn ($state) => $state?->format())->placeholder('—')->alignEnd(),
                TextColumn::make('options')->label('Options')->formatStateUsing(fn ($state) => $state instanceof LineOption ? $state->code() : $state)->badge()->color('gray'),
                TextColumn::make('holder')->label('Titulaire')->state(fn (SimLine $record): ?string => $record->currentAssignment?->user?->name)->placeholder('—'),
                TextColumn::make('iccid')->label('ICCID')->fontFamily(FontFamily::Mono)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(SimLineStatus::class)->multiple(),
                SelectFilter::make('plan_type')->label('Forfait')->options(PlanType::class),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSimLines::route('/')];
    }
}
