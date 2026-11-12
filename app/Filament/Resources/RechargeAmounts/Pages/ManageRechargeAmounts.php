<?php

namespace App\Filament\Resources\RechargeAmounts\Pages;

use App\Filament\Resources\RechargeAmounts\RechargeAmountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRechargeAmounts extends ManageRecords
{
    protected static string $resource = RechargeAmountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
