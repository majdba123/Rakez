<?php

namespace App\Filament\Admin\Resources\HrTeams\Pages;

use App\Filament\Admin\Resources\HrTeams\HrTeamResource;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Team\TeamService;
use Filament\Resources\Pages\CreateRecord;

class CreateHrTeam extends CreateRecord
{
    protected static string $resource = HrTeamResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return app(TeamService::class)->storeTeam($data, $actor->id);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        app(GovernanceAuditLogger::class)->log('governance.hr.team.created', $record, [
            'after' => [
                'name' => $record->name,
                'code' => $record->code,
            ],
        ], $actor);
    }
}
