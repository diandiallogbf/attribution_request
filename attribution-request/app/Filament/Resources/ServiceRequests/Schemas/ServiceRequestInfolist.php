<?php

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Enums\EmployeeCategory;
use App\Enums\EquipmentType;
use App\Enums\RequestType;
use App\Models\Application;
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
        $isTelephony = fn (ServiceRequest $record): bool => $record->type === RequestType::Telephony;
        $isProfile = fn (ServiceRequest $record): bool => $record->type === RequestType::UserProfile;

        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Bénéficiaire')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('beneficiary_name')->label('Nom')->state(fn (ServiceRequest $record): string => $record->beneficiaryName()),
                        TextEntry::make('beneficiary_snapshot.matricule')->label('Matricule')->placeholder('—'),
                        TextEntry::make('category')->label('Catégorie')->badge()->placeholder('—')
                            ->state(fn (ServiceRequest $record): ?EmployeeCategory => EmployeeCategory::tryFrom($record->beneficiary_snapshot['category'] ?? '') ?? $record->beneficiary?->category),
                        TextEntry::make('beneficiary_snapshot.job_title')->label('Fonction')->placeholder('—'),
                        TextEntry::make('beneficiary_snapshot.department')->label('Service')->placeholder('—'),
                        TextEntry::make('beneficiary_snapshot.site')->label('Site')->placeholder('—'),
                        TextEntry::make('beneficiary_snapshot.last_phone_assigned_at')->label('Dernier téléphone attribué')->date('d/m/Y')->placeholder('Aucun')->visible($isTelephony),
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

                Section::make('Fiche profil')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->visible($isProfile)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('profileDetail.action')->label('Demande')->badge(),
                        TextEntry::make('profileDetail.profile_type')->label('Type de profil')->placeholder('—'),
                        TextEntry::make('profileDetail.effective_at')->label('Date d\'effet')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('profileDetail.manager.name')->label('Responsable (N+1)')->placeholder('—'),
                        TextEntry::make('profileDetail.internship_ends_at')->label('Fin de stage')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('profileDetail.reference_profile')->label('Profil de référence')->placeholder('—'),
                        TextEntry::make('access')->label('Accès')
                            ->state(fn (ServiceRequest $record): ?string => implode(', ', $record->profileDetail?->baseAccessLabels() ?? []) ?: null)
                            ->placeholder('Aucun'),
                        TextEntry::make('profileDetail.distribution_lists')->label('Listes de diffusion')->placeholder('—')->columnSpan(2),
                        TextEntry::make('applications')->label('Applications')
                            ->state(fn (ServiceRequest $record): array => $record->profileDetail?->applications
                                ->map(fn (Application $app) => $app->name.($app->pivot->executed_at ? ' ✓' : ''))
                                ->all() ?? [])
                            ->badge()
                            ->color('violet')
                            ->placeholder('Aucune')
                            ->columnSpanFull(),
                        TextEntry::make('profileDetail.other_application')->label('Autre application')->placeholder('—'),
                        TextEntry::make('equipment')->label('Matériels')
                            ->state(fn (ServiceRequest $record): array => $record->profileDetail?->equipment?->map(fn (EquipmentType $type) => $type->getLabel())->all() ?? [])
                            ->badge()
                            ->placeholder('Aucun')
                            ->columnSpan(2),
                        TextEntry::make('profileDetail.comment')->label('Commentaires')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Exécution')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->visible(fn (ServiceRequest $record): bool => $record->executed_at !== null)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('executed_at')->label('Date d\'exécution')->dateTime('d/m/Y H:i'),
                        TextEntry::make('executedBy.name')->label('Exécuté par'),
                        TextEntry::make('profileDetail.execution_ticket')->label('Ticket')->placeholder('—')->visible($isProfile),
                        TextEntry::make('execution_notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]),

            Grid::make(1)->columnSpan(1)->schema([
                Section::make('Suivi')
                    ->schema([
                        TextEntry::make('type')->label('Fiche'),
                        TextEntry::make('status')->label('Statut')->badge(),
                        TextEntry::make('reference')->label('Référence')->copyable(),
                        TextEntry::make('submitted_at')->label('Soumise le')->dateTime('d/m/Y H:i'),
                        TextEntry::make('handler.name')->label('Traitée par')
                            ->visible(fn (ServiceRequest $record): bool => $record->handler_id !== null)
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->color('violet')
                            ->helperText(fn (ServiceRequest $record): ?string => $record->delegated_at
                                ? 'Déléguée par '.($record->delegatedBy?->name ?? 'la DSI').' le '.$record->delegated_at->format('d/m/Y à H:i')
                                : null),
                        TextEntry::make('delegation_note')->label('Consignes de la DSI')
                            ->visible(fn (ServiceRequest $record): bool => filled($record->delegation_note))
                            ->color('gray'),
                        TextEntry::make('allowance')->label('Grille de dotation')
                            ->visible(fn (ServiceRequest $record): bool => $isTelephony($record) && $record->beneficiary !== null)
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
                        TextEntry::make('orders')->label('Commandes')
                            ->visible(fn (ServiceRequest $record): bool => $record->orderItems->isNotEmpty())
                            ->state(fn (ServiceRequest $record): array => $record->orderItems
                                ->map(fn ($item) => $item->order->type->getLabel().($item->order->reference ? ' n° '.$item->order->reference : '').' — '.$item->order->status->getLabel())
                                ->all())
                            ->listWithLineBreaks(),
                    ]),

                Section::make('Validations')
                    ->schema([
                        RepeatableEntry::make('approvals')
                            ->hiddenLabel()
                            ->contained(false)
                            ->schema([
                                TextEntry::make('label')->hiddenLabel()->weight('medium')->state(fn ($record): string => $record->label()),
                                TextEntry::make('decision')->hiddenLabel()->badge(),
                                TextEntry::make('decidedBy.name')->hiddenLabel()->placeholder(fn ($record): string => $record->assignedTo?->name ?? 'Équipe DSI')
                                    ->helperText(fn ($record): ?string => $record->decided_at?->format('d/m/Y H:i')),
                                TextEntry::make('comment')->hiddenLabel()->placeholder('')->color('gray'),
                            ]),
                    ]),
            ]),
        ]);
    }
}
