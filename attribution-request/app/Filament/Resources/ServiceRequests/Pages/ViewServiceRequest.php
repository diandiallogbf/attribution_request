<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Actions\Fulfilment\ExecuteProfileRequest;
use App\Actions\Fulfilment\FulfilTelephonyRequest;
use App\Actions\Fulfilment\PlaceOrder;
use App\Actions\Fulfilment\ServeFromStock;
use App\Actions\Fulfilment\TransmitAndArchive;
use App\Actions\Requests\ApproveByDsi;
use App\Actions\Requests\DecideApproval;
use App\Data\ProfileExecutionData;
use App\Data\TelephonyFulfilmentData;
use App\Enums\ApprovalDecision;
use App\Enums\DeviceStatus;
use App\Enums\OrderType;
use App\Enums\PhoneReason;
use App\Enums\ProfileAction;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\Role;
use App\Enums\SimAction;
use App\Enums\SimLineStatus;
use App\Exceptions\Domain\DomainException;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\RechargeAmount;
use App\Models\ServiceRequest;
use App\Models\SimLine;
use App\ValueObjects\MoneyGnf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Date;

/**
 * Fiche d'une demande dans le back-office : chaque étape du processus y est une action,
 * visible seulement au bon statut et pour le bon rôle.
 *
 * @property ServiceRequest $record
 */
class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function resolveRecord(int|string $key): ServiceRequest
    {
        /** @var ServiceRequest $record */
        $record = parent::resolveRecord($key);

        return $record->loadMissing([
            'beneficiary', 'requester', 'executedBy', 'approvals.decidedBy', 'approvals.assignedTo', 'approvals.direction',
            'phoneDetail.previousDevice.model', 'phoneDetail.approvedDeviceModel', 'simDetail.existingSimLine',
            'profileDetail.applications', 'profileDetail.department', 'profileDetail.site', 'profileDetail.manager',
            'orderItems.order', 'archivedForms',
        ]);
    }

    public function getTitle(): string
    {
        return $this->record->displayReference().' — '.$this->record->subjectLabel();
    }

    public function getSubheading(): string
    {
        return $this->record->type->formTitle().' · '.$this->record->beneficiaryName();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->approvalActions(),
            ...$this->telephonyFulfilmentActions(),
            ...$this->profileFulfilmentActions(),
            ActionGroup::make([
                Action::make('form')
                    ->label('Fiche officielle (PDF)')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (): string => route('requests.form', $this->record), shouldOpenInNewTab: true),
                Action::make('portal')
                    ->label('Vue collaborateur')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (): string => route('requests.show', $this->record), shouldOpenInNewTab: true),
            ])->icon(Heroicon::EllipsisVertical)->color('gray'),
        ];
    }

    // ── Étape 03 : approbation DSI ───────────────────────────────────────────

    /** @return list<Action> */
    private function approvalActions(): array
    {
        $awaitingMe = fn (): bool => $this->record->status->isAwaitingApproval() && auth()->user()->can('decide', $this->record);
        $isTelephonyDsi = fn (): bool => $awaitingMe() && $this->record->status === RequestStatus::PendingDsi && $this->record->type === RequestType::Telephony;

        return [
            Action::make('return')
                ->label('Renvoyer')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible($awaitingMe)
                ->schema([Textarea::make('comment')->label('Motif du renvoi')->required()->rows(3)])
                ->action(fn (array $data) => $this->decide(ApprovalDecision::Returned, $data['comment'])),

            Action::make('reject')
                ->label('Rejeter')
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger')
                ->visible($awaitingMe)
                ->requiresConfirmation()
                ->modalDescription('La demande sera définitivement close. Le demandeur sera informé.')
                ->schema([Textarea::make('comment')->label('Motif du rejet')->required()->rows(3)])
                ->action(fn (array $data) => $this->decide(ApprovalDecision::Rejected, $data['comment'])),

            Action::make('approveProfile')
                ->label('Valider')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible(fn (): bool => $awaitingMe() && ! $isTelephonyDsi())
                ->schema([Textarea::make('comment')->label('Commentaire')->rows(2)])
                ->action(fn (array $data) => $this->decide(ApprovalDecision::Approved, $data['comment'] ?? null)),

            Action::make('approve')
                ->label('Approuver')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible($isTelephonyDsi)
                ->modalHeading('Approbation DSI')
                ->modalDescription('Vérifiez la grille de dotation, choisissez le modèle et le montant retenus.')
                ->fillForm(fn (): array => [
                    'approved_monthly_recharge_gnf' => $this->record->simDetail?->requested_monthly_recharge_gnf?->amount,
                ])
                ->schema([
                    Select::make('approved_device_model_id')
                        ->label('Modèle attribué')
                        ->visible(fn (): bool => (bool) $this->record->phoneDetail?->reason?->requiresNewDevice())
                        ->required(fn (): bool => (bool) $this->record->phoneDetail?->reason?->requiresNewDevice())
                        ->options(fn (): array => DeviceModel::query()->active()
                            ->when($this->record->phoneDetail?->requested_phone_type, fn ($query, $type) => $query->orderByRaw('phone_type = ? desc', [$type->value]))
                            ->get()
                            ->mapWithKeys(fn (DeviceModel $model) => [$model->id => $model->label.' — '.$model->phone_type->getLabel()])
                            ->all())
                        ->searchable()
                        ->helperText(fn (): string => 'Demandé : '.($this->record->phoneDetail?->requestedTypeLabel() ?? '—')),
                    Select::make('approved_monthly_recharge_gnf')
                        ->label('Recharge mensuelle approuvée')
                        ->visible(fn (): bool => (bool) $this->record->simDetail?->action?->requiresPlan())
                        ->required(fn (): bool => (bool) $this->record->simDetail?->action?->requiresPlan())
                        ->options(fn (): array => $this->rechargeOptions()),
                    Textarea::make('comment')
                        ->label('Commentaire / justification de dérogation')
                        ->helperText('Obligatoire si le choix sort de la grille de dotation.')
                        ->rows(3),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(ApproveByDsi::class)->handle(
                    $this->record,
                    auth()->user(),
                    isset($data['approved_device_model_id']) ? DeviceModel::query()->find($data['approved_device_model_id']) : null,
                    isset($data['approved_monthly_recharge_gnf']) ? MoneyGnf::from((int) $data['approved_monthly_recharge_gnf']) : null,
                    $data['comment'] ?? null,
                ), 'Demande approuvée', 'Le demandeur a été notifié.')),
        ];
    }

    // ── Étapes 04-05 : commande, stock, attribution (téléphonie) ──────────────

    /** @return list<Action> */
    private function telephonyFulfilmentActions(): array
    {
        $isTelephony = fn (): bool => $this->record->type === RequestType::Telephony && auth()->user()->hasAnyRole(Role::fulfilment());
        $needsDevice = fn (): bool => (bool) $this->record->phoneDetail?->reason?->requiresNewDevice();
        $needsNewLine = fn (): bool => $this->record->simDetail?->action === SimAction::Creation;

        return [
            Action::make('order')
                ->label('Commander')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->color('gray')
                ->visible(fn (): bool => $isTelephony()
                    && in_array($this->record->status, [RequestStatus::Approved, RequestStatus::Ordering], true)
                    && ($needsDevice() || $needsNewLine()))
                ->modalHeading('Étape 04 — Commande')
                ->schema([
                    Select::make('type')
                        ->label('Type de commande')
                        ->options(fn (): array => collect(OrderType::cases())
                            ->filter(fn (OrderType $type) => $type === OrderType::DevicePurchase ? $needsDevice() : $needsNewLine())
                            ->mapWithKeys(fn (OrderType $type) => [$type->value => $type->getLabel()])
                            ->all())
                        ->default(fn (): string => $needsDevice() ? OrderType::DevicePurchase->value : OrderType::OperatorRequest->value)
                        ->live()
                        ->required(),
                    TextInput::make('reference')->label(fn (Get $get): string => $get('type') === OrderType::OperatorRequest->value ? 'Référence opérateur' : 'N° de bon de commande'),
                    TextInput::make('supplier')->label('Fournisseur')->visible(fn (Get $get): bool => $get('type') === OrderType::DevicePurchase->value),
                    TextInput::make('unit_price')->label('Montant (GNF)')->numeric()->minValue(0),
                    DatePicker::make('expected_at')->label('Livraison prévue')->native(false)->minDate(now()),
                    Textarea::make('notes')->label('Notes')->rows(2),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(PlaceOrder::class)->handle(
                    $this->record,
                    auth()->user(),
                    OrderType::from($data['type']),
                    $data['reference'] ?? null,
                    $data['supplier'] ?? null,
                    isset($data['expected_at']) ? Date::parse($data['expected_at']) : null,
                    filled($data['unit_price'] ?? null) ? MoneyGnf::from((int) $data['unit_price']) : null,
                    $data['notes'] ?? null,
                ), 'Commande enregistrée', 'Réceptionnez-la depuis « Commandes » à son arrivée.')),

            Action::make('stock')
                ->label('Servir depuis le stock')
                ->icon(Heroicon::OutlinedCube)
                ->color('gray')
                ->visible(fn (): bool => $isTelephony() && $this->record->status === RequestStatus::Approved && ($needsDevice() || $needsNewLine()))
                ->requiresConfirmation()
                ->modalDescription('Le matériel nécessaire est disponible en stock : la demande passe « Prête à attribuer ».')
                ->action(fn () => $this->run(fn () => app(ServeFromStock::class)->handle($this->record, auth()->user()), 'Prête à attribuer')),

            Action::make('fulfil')
                ->label(fn (): string => $needsDevice() || $needsNewLine() || $this->record->simDetail?->action === SimAction::Transfer ? 'Attribuer' : 'Exécuter')
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->visible(fn (): bool => $isTelephony()
                    && ($this->record->status === RequestStatus::ReadyForAssignment
                        || ($this->record->status === RequestStatus::Approved && ! $needsDevice() && ! $needsNewLine())))
                ->modalHeading('Étape 05 — Attribution')
                ->modalDescription('Configurez le matériel puis remettez-le : le bénéficiaire confirmera la réception dans le portail.')
                ->modalWidth('2xl')
                ->schema([
                    Select::make('device_id')
                        ->label('Téléphone remis')
                        ->visible($needsDevice)
                        ->required($needsDevice)
                        ->searchable()
                        ->options(fn (): array => Device::query()
                            ->where('status', DeviceStatus::InStock)
                            ->with('model')
                            ->get()
                            ->sortByDesc(fn (Device $device) => $device->device_model_id === $this->record->phoneDetail?->approved_device_model_id)
                            ->mapWithKeys(fn (Device $device) => [$device->id => $device->label])
                            ->all())
                        ->helperText(fn (): string => 'Modèle approuvé : '.($this->record->phoneDetail?->approvedDeviceModel?->label ?? '—')),
                    CheckboxList::make('checklist')
                        ->label('Configuration (MS)')
                        ->visible($needsDevice)
                        ->options(TelephonyFulfilmentData::CHECKLIST)
                        ->columns(2)
                        ->default(array_keys(TelephonyFulfilmentData::CHECKLIST)),
                    Select::make('previous_device_status')
                        ->label('Ancien téléphone devient')
                        ->visible(fn (): bool => $this->record->phoneDetail?->previousDevice !== null && $this->record->phoneDetail->reason !== PhoneReason::Repair)
                        ->options([
                            DeviceStatus::InStock->value => 'Récupéré en bon état (retour en stock)',
                            DeviceStatus::InRepair->value => 'Envoyé en réparation',
                            DeviceStatus::Retired->value => 'Hors service (réformé)',
                            DeviceStatus::Lost->value => 'Perdu / volé',
                        ])
                        ->default(DeviceStatus::InStock->value),
                    TextInput::make('return_condition')
                        ->label('État constaté')
                        ->visible(fn (): bool => $this->record->phoneDetail?->previousDevice !== null && $this->record->phoneDetail->reason !== PhoneReason::Repair),
                    Select::make('sim_line_id')
                        ->label('Ligne attribuée')
                        ->visible($needsNewLine)
                        ->required($needsNewLine)
                        ->searchable()
                        ->options(fn (): array => SimLine::query()->where('status', SimLineStatus::InStock)->get()
                            ->mapWithKeys(fn (SimLine $line) => [$line->id => $line->msisdn->international().($line->iccid ? ' — ICCID '.$line->iccid : '')])
                            ->all()),
                    Textarea::make('notes')->label('Notes d\'exécution')->rows(2),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(FulfilTelephonyRequest::class)->handle(
                    $this->record,
                    auth()->user(),
                    new TelephonyFulfilmentData(
                        deviceId: isset($data['device_id']) ? (int) $data['device_id'] : null,
                        setupChecklist: collect(TelephonyFulfilmentData::CHECKLIST)->keys()->mapWithKeys(fn (string $key) => [$key => in_array($key, $data['checklist'] ?? [], true)])->all(),
                        previousDeviceStatus: DeviceStatus::tryFrom((string) ($data['previous_device_status'] ?? '')),
                        returnCondition: $data['return_condition'] ?? null,
                        simLineId: isset($data['sim_line_id']) ? (int) $data['sim_line_id'] : null,
                        notes: $data['notes'] ?? null,
                    ),
                ), 'Exécution enregistrée', 'Le bénéficiaire a été notifié.')),
        ];
    }

    // ── Fiche profil : exécution IT puis transmission / archivage ─────────────

    /** @return list<Action> */
    private function profileFulfilmentActions(): array
    {
        $isProfile = fn (): bool => $this->record->type === RequestType::UserProfile;
        $action = fn (): ?ProfileAction => $this->record->profileDetail?->action;

        return [
            Action::make('execute')
                ->label('Exécuter')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->visible(fn (): bool => $isProfile() && $this->record->status === RequestStatus::Approved && auth()->user()->hasAnyRole(Role::fulfilment()))
                ->modalHeading('Création / mise à jour du profil')
                ->modalDescription('Cochez les accès effectivement traités et joignez la preuve (capture ou n° de ticket).')
                ->modalWidth('2xl')
                ->fillForm(fn (): array => [
                    'applications' => $this->record->profileDetail?->applications->pluck('id')->all() ?? [],
                    'create_account' => $action() === ProfileAction::Creation,
                    'deactivate_account' => $action() === ProfileAction::Deletion,
                ])
                ->schema([
                    CheckboxList::make('applications')
                        ->label('Applications traitées')
                        ->options(fn (): array => $this->record->profileDetail?->applications->pluck('name', 'id')->all() ?? [])
                        ->visible(fn (): bool => (bool) $this->record->profileDetail?->applications->isNotEmpty())
                        ->columns(2)
                        ->bulkToggleable(),
                    TextInput::make('ticket')->label('N° de ticket / référence'),
                    FileUpload::make('proof')
                        ->label('Capture d\'écran / preuve')
                        ->disk('local')
                        ->directory('execution-proofs')
                        ->visibility('private')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'application/pdf'])
                        ->maxSize(5120)
                        ->storeFileNamesIn('proof_name'),
                    Toggle::make('create_account')
                        ->label('Créer son compte sur le portail')
                        ->visible(fn (): bool => $action() === ProfileAction::Creation)
                        ->live(),
                    TextInput::make('account_email')
                        ->label('Adresse e-mail créée')
                        ->email()
                        ->visible(fn (Get $get): bool => $action() === ProfileAction::Creation && (bool) $get('create_account'))
                        ->required(fn (Get $get): bool => (bool) $get('create_account')),
                    Toggle::make('deactivate_account')
                        ->label('Désactiver son compte sur le portail')
                        ->visible(fn (): bool => $action() === ProfileAction::Deletion),
                    Textarea::make('notes')->label('Commentaires d\'exécution')->rows(2),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(ExecuteProfileRequest::class)->handle(
                    $this->record,
                    auth()->user(),
                    new ProfileExecutionData(
                        executedApplicationIds: array_map('intval', $data['applications'] ?? []),
                        ticket: $data['ticket'] ?? null,
                        notes: $data['notes'] ?? null,
                        proofPath: $data['proof'] ?? null,
                        proofOriginalName: $data['proof_name'] ?? null,
                        createPortalAccount: (bool) ($data['create_account'] ?? false),
                        accountEmail: $data['account_email'] ?? null,
                        deactivatePortalAccount: (bool) ($data['deactivate_account'] ?? false),
                    ),
                ), 'Profil exécuté', 'L\'assistante a été notifiée pour la transmission.')),

            Action::make('archive')
                ->label('Transmettre et archiver')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->visible(fn (): bool => $isProfile() && $this->record->status === RequestStatus::Executed && auth()->user()->hasAnyRole(Role::archiving()))
                ->requiresConfirmation()
                ->modalHeading('Étape 06 — Clôture')
                ->modalDescription('La fiche officielle (PDF) sera générée avec toutes les validations horodatées puis archivée.')
                ->schema([Textarea::make('comment')->label('Commentaire')->rows(2)])
                ->action(fn (array $data) => $this->run(fn () => app(TransmitAndArchive::class)->handle($this->record, auth()->user(), $data['comment'] ?? null), 'Fiche archivée')),
        ];
    }

    // ── Outils ───────────────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function rechargeOptions(): array
    {
        return RechargeAmount::query()->active()->get()
            ->mapWithKeys(fn (RechargeAmount $amount) => [$amount->amount_gnf->amount => $amount->amount_gnf->format()])
            ->all();
    }

    private function decide(ApprovalDecision $decision, ?string $comment): void
    {
        $this->run(
            fn () => app(DecideApproval::class)->handle($this->record, auth()->user(), $decision, $comment),
            $decision->getLabel(),
            'Le demandeur a été notifié.',
        );
    }

    /** Exécute une action métier : succès → notification, erreur métier → message lisible. */
    private function run(callable $callback, string $successTitle, ?string $successBody = null): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->title())->body($exception->getMessage())->persistent()->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->body($successBody)->send();
        $this->redirect(ServiceRequestResource::getUrl('view', ['record' => $this->record]), navigate: true);
    }
}
