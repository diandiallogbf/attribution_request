<?php

namespace App\Filament\Resources\Orders;

use App\Actions\Fulfilment\ReceiveOrder;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\Role;
use App\Exceptions\Domain\DomainException;
use App\Filament\Concerns\RestrictedToRoles;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SimLine;
use App\Rules\UniqueValueObject;
use App\Rules\ValidValueObject;
use App\ValueObjects\Iccid;
use App\ValueObjects\Imei;
use App\ValueObjects\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Bons de commande et demandes opérateur (étape 04), réception en stock. */
class OrderResource extends Resource
{
    use RestrictedToRoles;

    protected static ?string $model = Order::class;

    protected static ?string $slug = 'commandes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Demandes';

    protected static ?string $navigationLabel = 'Commandes';

    protected static ?string $modelLabel = 'commande';

    protected static ?string $pluralModelLabel = 'commandes';

    protected static ?int $navigationSort = 2;

    protected static function allowedRoles(): array
    {
        return Role::fulfilment();
    }

    public static function canCreate(): bool
    {
        return false; // les commandes naissent depuis une demande approuvée
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->whereIn('status', [OrderStatus::Sent, OrderStatus::PartiallyReceived])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Commandes en attente de réception';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items.serviceRequest', 'items.deviceModel', 'operator', 'orderedBy']))
            ->defaultSort('ordered_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label('Référence')->fontFamily(FontFamily::Mono)->placeholder('—')->searchable(),
                TextColumn::make('type')->label('Type')->badge()->color('gray'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('content')->label('Contenu')
                    ->state(fn (Order $record): array => $record->items->map(fn (OrderItem $item) => $item->quantity.' × '.($item->deviceModel?->label ?? $item->description))->all())
                    ->description(fn (Order $record): ?string => $record->supplier ?? $record->operator?->name)
                    ->listWithLineBreaks()
                    ->wrap(),
                TextColumn::make('requests')->label('Demandes')
                    ->state(fn (Order $record): array => $record->items->pluck('serviceRequest')->filter()->map->displayReference()->unique()->values()->all())
                    ->badge()
                    ->url(fn (Order $record): ?string => ($request = $record->items->first()?->serviceRequest) ? ServiceRequestResource::getUrl('view', ['record' => $request]) : null),
                TextColumn::make('ordered_at')->label('Commandée')->date('d/m/Y')->sortable(),
                TextColumn::make('expected_at')->label('Livraison prévue')->date('d/m/Y')->placeholder('—')
                    ->color(fn (Order $record): ?string => $record->expected_at?->isPast() && $record->status === OrderStatus::Sent ? 'danger' : null),
                TextColumn::make('total_gnf')->label('Montant')->formatStateUsing(fn ($state) => $state?->format())->placeholder('—')->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(OrderStatus::class),
                SelectFilter::make('type')->label('Type')->options(OrderType::class),
            ])
            ->recordActions([
                Action::make('receive')
                    ->label('Réceptionner')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->button()
                    ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Sent, OrderStatus::PartiallyReceived], true))
                    ->modalHeading(fn (Order $record): string => 'Réception -'.$record->type->getLabel())
                    ->modalDescription('Le matériel reçu entre en stock ; les demandes concernées passent « Prête à attribuer ».')
                    ->fillForm(fn (Order $record): array => [
                        'devices' => $record->type === OrderType::DevicePurchase ? array_fill(0, (int) $record->items->sum('quantity'), ['imei' => null, 'serial_number' => null]) : [],
                        'lines' => $record->type === OrderType::OperatorRequest ? array_fill(0, (int) $record->items->sum('quantity'), ['msisdn' => null, 'iccid' => null]) : [],
                    ])
                    ->schema([
                        Repeater::make('devices')
                            ->label('Appareils reçus')
                            ->visible(fn (Order $record): bool => $record->type === OrderType::DevicePurchase)
                            ->schema([
                                TextInput::make('imei')->label('IMEI')->required()
                                    ->rules([new ValidValueObject(Imei::class), new UniqueValueObject(Imei::class, Device::class, 'imei', message: 'Cet IMEI est déjà dans le parc.')]),
                                TextInput::make('serial_number')->label('N° de série'),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                        Repeater::make('lines')
                            ->label('Lignes reçues')
                            ->visible(fn (Order $record): bool => $record->type === OrderType::OperatorRequest)
                            ->schema([
                                TextInput::make('msisdn')->label('Numéro')->required()
                                    ->rules([new ValidValueObject(PhoneNumber::class), new UniqueValueObject(PhoneNumber::class, SimLine::class, 'msisdn', message: 'Ce numéro est déjà dans le parc.')]),
                                TextInput::make('iccid')->label('ICCID')->rules([new ValidValueObject(Iccid::class)]),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ])
                    ->action(function (Order $record, array $data): void {
                        try {
                            app(ReceiveOrder::class)->handle($record, auth()->user(), array_values($data['devices'] ?? []), array_values($data['lines'] ?? []));
                        } catch (DomainException $exception) {
                            Notification::make()->danger()->title($exception->title())->body($exception->getMessage())->persistent()->send();

                            return;
                        }

                        Notification::make()->success()->title('Commande réceptionnée')->body('Le matériel est en stock.')->send();
                    }),
            ])
            ->emptyStateHeading('Aucune commande')
            ->emptyStateDescription('Les commandes se passent depuis une demande approuvée (action « Commander »).')
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
        ];
    }
}
