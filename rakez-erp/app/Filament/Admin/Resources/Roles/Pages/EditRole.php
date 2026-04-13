<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Services\Governance\RoleGovernanceService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $record */
        $record = $this->getRecord();
        $data['permissions'] = $record->permissions()->pluck('name')->sort()->values()->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(RoleGovernanceService::class)->syncPermissions($record, $data['permissions'] ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
