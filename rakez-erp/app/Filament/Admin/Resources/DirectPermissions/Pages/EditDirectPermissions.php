<?php

namespace App\Filament\Admin\Resources\DirectPermissions\Pages;

use App\Filament\Admin\Resources\DirectPermissions\DirectPermissionResource;
use App\Models\User;
use App\Services\Governance\DirectPermissionGovernanceService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDirectPermissions extends EditRecord
{
    protected static string $resource = DirectPermissionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();
        $data['direct_permissions'] = $record->permissions()->pluck('name')->sort()->values()->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(DirectPermissionGovernanceService::class)->sync($record, $data['direct_permissions'] ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
