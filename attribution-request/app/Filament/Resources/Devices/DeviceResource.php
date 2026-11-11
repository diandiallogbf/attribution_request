<?php

namespace App\Filament\Resources\Devices;

use App\Enums\DeviceStatus;
use App\Enums\Role;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Devices\Pages\ManageDevices;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Rules\UniqueValueObject;
use App\Rules\ValidValueObject;
use App\ValueObjects\Imei;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DeviceResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = Device::class;

    protected static ?string $slug = 'parc/telephones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Parc';

    protected static ?string $navigationLabel = 'Téléphones & tablettes';

    protected static ?string $modelLabel = 'appareil';

    protected static ?string $pluralModelLabel = 'appareils';

    protected static ?int $navigationSort = 1;

    protected static function allowedRoles(): array
    {
        return Role::fulfilment();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Device::query()->where('status', DeviceStatus::InStock)->count();
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'En stock';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('device_model_id')->label('Modèle')->required()->searchable()
                ->options(fn () => DeviceModel::query()->orderBy('brand')->get()->mapWithKeys(fn (DeviceModel $model) => [$model->id => $model->label])->all()),
            Select::make('status')->label('Statut')->options(DeviceStatus::class)->default(DeviceStatus::InStock->value)->required(),
            TextInput::make('imei')->label('IMEI')->helperText('15 chiffres -*#06# sur le téléphone')
                ->rules(fn (?Device $record) => [new ValidValueObject(Imei::class), new UniqueValueObject(Imei::class, Device::class, 'imei', $record, 'Cet IMEI est déjà dans le parc.')]),
            TextInput::make('serial_number')->label('N° de série')->unique(ignoreRecord: true),
            DatePicker::make('acquired_at')->label('Date d\'acquisition')->native(false),
            Textarea::make('notes')->label('Notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['model', 'currentAssignment.user']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('model.label')->label('Modèle')
                    ->description(fn (Device $record): string => $record->model->phone_type->getLabel())
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('model', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%"))),
                TextColumn::make('imei')->label('IMEI')->fontFamily(FontFamily::Mono)->formatStateUsing(fn ($state) => $state?->formatted())->placeholder('—')->searchable()->copyable(),
                TextColumn::make('serial_number')->label('N° de série')->fontFamily(FontFamily::Mono)->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('status')->label('Statut')->badge()->sortable(),
                TextColumn::make('holder')->label('Détenteur')
                    ->state(fn (Device $record): ?string => $record->currentAssignment?->user?->name)
                    ->description(fn (Device $record): ?string => $record->currentAssignment ? 'depuis le '.$record->currentAssignment->assigned_at->format('d/m/Y') : null)
                    ->placeholder('—'),
                TextColumn::make('acquired_at')->label('Acquis le')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(DeviceStatus::class)->multiple(),
                SelectFilter::make('device_model_id')->label('Modèle')->relationship('model', 'name'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDevices::route('/')];
    }
}
