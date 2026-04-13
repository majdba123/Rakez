<?php

namespace App\Filament\Admin\Resources\GovernanceTemporaryPermissions\Pages;

use App\Filament\Admin\Resources\GovernanceTemporaryPermissions\GovernanceTemporaryPermissionResource;
use App\Models\User;
use App\Services\Governance\GovernanceTemporaryPermissionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGovernanceTemporaryPermission extends CreateRecord
{
    protected static string $resource = GovernanceTemporaryPermissionResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $subject = User::query()->findOrFail($data['user_id']);

        return app(GovernanceTemporaryPermissionService::class)->grant(
            $subject,
            $data['permission'],
            $actor,
            \Carbon\Carbon::parse($data['expires_at']),
            $data['reason'] ?? null,
        );
    }
}
