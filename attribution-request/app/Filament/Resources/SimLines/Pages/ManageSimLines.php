<?php

namespace App\Filament\Resources\SimLines\Pages;

use App\Filament\Resources\SimLines\SimLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSimLines extends ManageRecords
{
    protected static string $resource = SimLineResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ajouter une ligne')];
    }
}
