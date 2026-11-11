<?php

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Models\ServiceRequest;
use App\Services\AllowanceChecker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ServiceRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Bénéficiaire')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('beneficiary.name')->label('Nom'),
                        TextEntry::make('beneficiary_snapshot.matricule')->label('Matricule')->placeholder('—'),
                        TextEntry::make('beneficiary.category')->label('Catégorie')->badge(),
                        TextEntry::make('beneficiary_snapshot.job_title')->label('Fonction')->placeholder('—'),
                        TextEntry::make('beneficiary_snapshot.department')->label('Service')->placeholder('—'),
                        TextEntry::make('beneficiary_snapshot.site')->label('Site')->placeholder('—'),
                        TextEntry::make('beneficiary_snapshot.last_phone_assigned_at')->label('Dernier téléphone attribué')->date('d/m/Y')->placeholder('Aucun'),
                        TextEntry::make('requester.name')->label('Demandeur'),
                    ]),

                Section::make('Téléphone')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->visible(fn (ServiceRequest $record): bool => $record->phoneDetail !== null)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('phoneDetail.reason')->label('Motif')->badge(),
                        TextEntry::make('phone_type')->label('Type demandé')
                            ->state(fn (ServiceRequest $record): ?string => $record->phoneDetail?->requestedTypeLabel())
                            ->placeholder('—'),
                        TextEntry::make('phoneDetail.approvedDeviceModel.label')->label('Modèle retenu')->placeholder('À définir'),
                        TextEntry::make('previous_device')->label('Appareil concerné')
                            ->state(fn (ServiceRequest $record): ?string => $record->phoneDetail?->previousDevice?->label
                                ?? (trim($record->phoneDetail?->previous_device_label.' '.$record->phoneDetail?->previous_device_serial) ?: null))
                            ->placeholder('—'),
                        TextEntry::make('phoneDetail.allowance_override_reason')->label('Dérogation')->placeholder('—'),
                        TextEntry::make('phoneDetail.detailed_reason')->label('Motif détaillé')->columnSpanFull(),
                    ]),

                Section::make('Ligne SIM')
                    ->icon(Heroicon::OutlinedSignal)
                    ->visible(fn (ServiceRequest $record): bool => $record->simDetail !== null)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('simDetail.action')->label('Action')->badge(),
                        TextEntry::make('simDetail.plan_type')->label('Forfait')->placeholder('—'),
                        TextEntry::make('line')->label('Ligne concernée')
                            ->state(fn (ServiceRequest $record): ?string => $record->simDetail?->existingSimLine?->msisdn->international()
                                ?? $record->simDetail?->existing_msisdn?->international())
                            ->placeholder('—'),
                        TextEntry::make('options')->label('Options')
                            ->state(fn (ServiceRequest $record): ?string => $record->simDetail?->options?->map(fn ($o) => $o->code())->join(', ') ?: null)
                            ->placeholder('Aucune'),
                        TextEntry::make('requested_recharge')->label('Recharge demandée')
                            ->state(fn (ServiceRequest $record): ?string => $record->simDetail?->requested_monthly_recharge_gnf?->format())
                            ->placeholder('—'),
                        TextEntry::make('approved_recharge')->label('Recharge approuvée')
                            ->state(fn (ServiceRequest $record): ?string => $record->simDetail?->approved_monthly_recharge_gnf?->format())
                            ->placeholder('À définir'),
                    ]),
            ]),

            Grid::make(1)->columnSpan(1)->schema([
                Section::make('Suivi')
                    ->schema([
                        TextEntry::make('status')->label('Statut')->badge(),
                        TextEntry::make('reference')->label('Référence')->copyable(),
                        TextEntry::make('submitted_at')->label('Soumise le')->dateTime('d/m/Y H:i'),
                        TextEntry::make('allowance')->label('Grille de dotation')
                            ->state(function (ServiceRequest $record): string {
                                $phone = $record->phoneDetail;
                                $check = app(AllowanceChecker::class)->check(
                                    $record->beneficiary,
                                    $phone?->reason?->requiresNewDevice() ? $phone->requested_phone_type : null,
                                    $record->simDetail?->requested_monthly_recharge_gnf,
                                );

                                return $check->isCompliant() ? 'Conforme' : implode(' ', $check->violations);
                            })
                            ->color(fn (string $state): string => $state === 'Conforme' ? 'success' : 'warning')
                            ->icon(fn (string $state): Heroicon => $state === 'Conforme' ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedScale),
                    ]),

                Section::make('Validations')
                    ->schema([
                        RepeatableEntry::make('approvals')
                            ->hiddenLabel()
                            ->contained(false)
                            ->schema([
                                TextEntry::make('step')->hiddenLabel()->weight('medium'),
                                TextEntry::make('decision')->hiddenLabel()->badge(),
                                TextEntry::make('decidedBy.name')->hiddenLabel()->placeholder('En attente')
                                    ->helperText(fn ($record): ?string => $record->decided_at?->format('d/m/Y H:i')),
                                TextEntry::make('comment')->hiddenLabel()->placeholder('')->color('gray'),
                            ]),
                    ]),
            ]),
        ]);
    }
}
