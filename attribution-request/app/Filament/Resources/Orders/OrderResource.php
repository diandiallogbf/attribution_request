<?php

namespace App\Filament\Resources\Orders;

use App\Actions\Finance\RecordOrderAccounting;
use App\Actions\Fulfilment\ReceiveOrder;
use App\Data\OrderAccountingData;
use App\Enums\AccountingStatus;
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
use App\ValueObjects\MoneyGnf;
use App\ValueObjects\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
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
        return array_values(array_unique([...Role::fulfilment(), ...Role::financeReaders()], SORT_REGULAR));
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
        $isFinanceReader = fn (): bool => (bool) auth()->user()?->hasAnyRole(Role::financeReaders());
        $sumFormat = fn ($state): string => MoneyGnf::from((int) $state)->format();

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
                TextColumn::make('total_gnf')->label('Montant BC')->formatStateUsing(fn ($state) => $state?->format())->placeholder('—')->alignEnd()
                    ->summarize(Sum::make()->label('Total BC')->formatStateUsing($sumFormat))
                    ->toggleable(isToggledHiddenByDefault: ! $isFinanceReader()),
                TextColumn::make('invoice_amount_gnf')->label('Facture')->formatStateUsing(fn ($state) => $state?->format())->placeholder('—')->alignEnd()
                    ->description(fn (Order $record): ?string => $record->invoice_reference
                        ? 'N° '.$record->invoice_reference.($record->invoiced_at ? ' du '.$record->invoiced_at->format('d/m/Y') : '')
                        : null)
                    ->summarize(Sum::make()->label('Total facturé')->formatStateUsing($sumFormat))
                    ->toggleable(isToggledHiddenByDefault: ! $isFinanceReader()),
                TextColumn::make('accounting')->label('Comptabilité')->badge()
                    ->state(fn (Order $record): AccountingStatus => $record->accountingStatus())
                    ->description(fn (Order $record): ?string => $record->paid_at ? 'Réglée le '.$record->paid_at->format('d/m/Y') : $record->cost_center)
                    ->toggleable(isToggledHiddenByDefault: ! $isFinanceReader()),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(OrderStatus::class),
                SelectFilter::make('type')->label('Type')->options(OrderType::class),
                SelectFilter::make('accounting')
                    ->label('Comptabilité')
                    ->options(AccountingStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        AccountingStatus::tryFrom((string) ($data['value'] ?? '')),
                        fn (Builder $query, AccountingStatus $status) => $status->applyTo($query),
                    )),
                Filter::make('period')
                    ->label('Période de commande')
                    ->schema([
                        DatePicker::make('from')->label('Commandées du')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('au')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('ordered_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('ordered_at', '<=', $date)))
                    ->indicateUsing(function (array $data): ?string {
                        $from = filled($data['from'] ?? null) ? Date::parse($data['from'])->format('d/m/Y') : null;
                        $until = filled($data['until'] ?? null) ? Date::parse($data['until'])->format('d/m/Y') : null;

                        return match (true) {
                            $from && $until => "Du {$from} au {$until}",
                            (bool) $from => "Depuis le {$from}",
                            (bool) $until => "Jusqu'au {$until}",
                            default => null,
                        };
                    }),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('accounting')
                    ->label('Comptabiliser')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->color('gray')
                    ->visible(fn (Order $record): bool => $record->status !== OrderStatus::Cancelled && (bool) auth()->user()?->hasAnyRole(Role::accounting()))
                    ->modalHeading(fn (Order $record): string => 'Comptabilité — '.($record->reference ?? $record->type->getLabel()))
                    ->modalSubmitActionLabel('Enregistrer')
                    ->modalDescription(fn (Order $record): string => 'Montant du bon de commande : '.($record->total_gnf?->format() ?? 'non renseigné').'. La facture fait foi dans les dépenses dès qu\'elle est saisie.')
                    ->fillForm(fn (Order $record): array => [
                        'invoice_reference' => $record->invoice_reference,
                        'invoiced_at' => $record->invoiced_at?->toDateString(),
                        'invoice_amount' => $record->invoice_amount_gnf?->amount ?? $record->total_gnf?->amount,
                        'cost_center' => $record->cost_center,
                        'paid_at' => $record->paid_at?->toDateString(),
                        'finance_notes' => $record->finance_notes,
                    ])
                    ->schema([Grid::make(2)->schema([
                        TextInput::make('invoice_reference')->label('N° de facture')->maxLength(100),
                        DatePicker::make('invoiced_at')->label('Date de facture')->native(false)->displayFormat('d/m/Y')->maxDate(now())
                            ->requiredWith('invoice_reference'),
                        TextInput::make('invoice_amount')->label('Montant facturé (GNF)')->numeric()->minValue(0)->step(1)
                            ->requiredWith('invoice_reference'),
                        TextInput::make('cost_center')->label('Imputation (compte / centre de coût)')->maxLength(30),
                        DatePicker::make('paid_at')->label('Réglée le')->native(false)->displayFormat('d/m/Y')->maxDate(now())
                            ->helperText('Laisser vide tant que la facture n\'est pas payée.'),
                        Textarea::make('finance_notes')->label('Notes comptables')->rows(2)->columnSpanFull(),
                    ])])
                    ->action(function (Order $record, array $data): void {
                        try {
                            app(RecordOrderAccounting::class)->handle($record, auth()->user(), new OrderAccountingData(
                                invoiceReference: $data['invoice_reference'] ?? null,
                                invoicedAt: filled($data['invoiced_at'] ?? null) ? Date::parse($data['invoiced_at']) : null,
                                invoiceAmount: filled($data['invoice_amount'] ?? null) ? MoneyGnf::from((int) $data['invoice_amount']) : null,
                                costCenter: $data['cost_center'] ?? null,
                                paidAt: filled($data['paid_at'] ?? null) ? Date::parse($data['paid_at']) : null,
                                notes: $data['finance_notes'] ?? null,
                            ));
                        } catch (DomainException $exception) {
                            Notification::make()->danger()->title($exception->title())->body($exception->getMessage())->persistent()->send();

                            return;
                        }

                        Notification::make()->success()->title('Écriture enregistrée')->send();
                    }),
                Action::make('receive')
                    ->label('Réceptionner')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->button()
                    ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Sent, OrderStatus::PartiallyReceived], true)
                        && (bool) auth()->user()?->hasAnyRole(Role::fulfilment())
                        && $record->items->pluck('serviceRequest')->filter()->every(fn ($request): bool => $request->canBeFulfilledBy(auth()->user())))
                    ->modalHeading(fn (Order $record): string => 'Réception — '.$record->type->getLabel())
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
