<?php

namespace App\Filament\Resources\DeviceModels\Pages;

use App\Filament\Resources\DeviceModels\DeviceModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeviceModels extends ManageRecords
{
    protected static string $resource = DeviceModelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
