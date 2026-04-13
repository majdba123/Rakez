<?php

namespace App\Filament\Admin\Resources\GovernanceTemporaryPermissions\Pages;

use App\Filament\Admin\Resources\GovernanceTemporaryPermissions\GovernanceTemporaryPermissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGovernanceTemporaryPermissions extends ListRecords
{
    protected static string $resource = GovernanceTemporaryPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
