<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Governance\GovernanceCatalog;
use App\Services\Governance\UserGovernanceService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();
        $catalog = app(GovernanceCatalog::class);

        $data['governance_roles'] = $record->roles
            ->pluck('name')
            ->intersect(config('governance.managed_governance_roles'))
            ->values()
            ->all();

        $data['additional_roles'] = $record->roles
            ->pluck('name')
            ->filter(fn (string $role): bool => $catalog->isSupplementalOperationalRole($role))
            ->values()
            ->all();

        $data['direct_permissions'] = $record->permissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $data['password'] = null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UserGovernanceService::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make('deleteUser')
                ->visible(fn (User $record): bool => UserResource::canDelete($record))
                ->action(function (User $record): void {
                    app(UserGovernanceService::class)->delete($record);
                }),
        ];
    }
}
