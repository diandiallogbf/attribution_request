<?php

namespace App\Filament\Resources\AllowanceRules\Pages;

use App\Filament\Resources\AllowanceRules\AllowanceRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAllowanceRules extends ManageRecords
{
    protected static string $resource = AllowanceRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
