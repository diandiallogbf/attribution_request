<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Actions\Requests\ApproveByDsi;
use App\Actions\Requests\DecideApproval;
use App\Enums\ApprovalDecision;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\DomainException;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\DeviceModel;
use App\Models\RechargeAmount;
use App\Models\ServiceRequest;
use App\ValueObjects\MoneyGnf;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
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
            'beneficiary', 'requester', 'approvals.decidedBy', 'approvals.assignedTo',
            'phoneDetail.previousDevice.model', 'phoneDetail.approvedDeviceModel', 'simDetail.existingSimLine',
        ]);
    }

    public function getTitle(): string
    {
        return $this->record->displayReference().' — '.$this->record->subjectLabel();
    }

    protected function getHeaderActions(): array
    {
        $awaitingDsi = fn (): bool => $this->record->status === RequestStatus::PendingDsi && auth()->user()->can('decide', $this->record);

        return [
            Action::make('return')
                ->label('Renvoyer')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible($awaitingDsi)
                ->schema([Textarea::make('comment')->label('Motif du renvoi')->required()->rows(3)])
                ->action(fn (array $data) => $this->decide(ApprovalDecision::Returned, $data['comment'])),

            Action::make('reject')
                ->label('Rejeter')
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger')
                ->visible($awaitingDsi)
                ->requiresConfirmation()
                ->modalDescription('La demande sera définitivement close. Le demandeur sera informé.')
                ->schema([Textarea::make('comment')->label('Motif du rejet')->required()->rows(3)])
                ->action(fn (array $data) => $this->decide(ApprovalDecision::Rejected, $data['comment'])),

            Action::make('approve')
                ->label('Approuver')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('primary')
                ->visible($awaitingDsi)
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
                        ->options(fn (): array => RechargeAmount::query()->active()->get()
                            ->mapWithKeys(fn (RechargeAmount $amount) => [$amount->amount_gnf->amount => $amount->amount_gnf->format()])
                            ->all()),
                    Textarea::make('comment')
                        ->label('Commentaire / justification de dérogation')
                        ->helperText('Obligatoire si le choix sort de la grille de dotation.')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $this->run(fn () => app(ApproveByDsi::class)->handle(
                        $this->record,
                        auth()->user(),
                        isset($data['approved_device_model_id']) ? DeviceModel::query()->find($data['approved_device_model_id']) : null,
                        isset($data['approved_monthly_recharge_gnf']) ? MoneyGnf::from((int) $data['approved_monthly_recharge_gnf']) : null,
                        $data['comment'] ?? null,
                    ), 'Demande approuvée');
                }),

            Action::make('portal')
                ->label('Vue collaborateur')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => route('requests.show', $this->record)),
        ];
    }

    private function decide(ApprovalDecision $decision, string $comment): void
    {
        $this->run(
            fn () => app(DecideApproval::class)->handle($this->record, auth()->user(), $decision, $comment),
            $decision->getLabel(),
        );
    }

    /** Exécute une action métier : succès → notification, erreur métier → message lisible. */
    private function run(callable $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->title())->body($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->body('Le demandeur a été notifié.')->send();
        $this->record->refresh();
        $this->redirect(ServiceRequestResource::getUrl('view', ['record' => $this->record]), navigate: true);
    }
}
