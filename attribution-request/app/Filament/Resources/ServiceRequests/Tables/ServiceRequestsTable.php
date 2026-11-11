<?php

namespace App\Filament\Resources\ServiceRequests\Tables;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('beneficiary.last_name')
                    ->label('Bénéficiaire')
                    ->formatStateUsing(fn (ServiceRequest $record): string => $record->beneficiary->name)
                    ->description(fn (ServiceRequest $record): ?string => $record->beneficiary->department?->name)
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('subject')
                    ->label('Objet')
                    ->state(fn (ServiceRequest $record): string => $record->subjectLabel())
                    ->description(fn (ServiceRequest $record): ?string => $record->phoneDetail?->reason?->getLabel() ?? $record->simDetail?->action?->getLabel()),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Soumise')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('site.name')
                    ->label('Site')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(RequestStatus::class)
                    ->multiple(),
                SelectFilter::make('site')
                    ->label('Site')
                    ->relationship('site', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('Aucune demande')
            ->emptyStateDescription('Les demandes soumises par les collaborateurs apparaîtront ici.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
