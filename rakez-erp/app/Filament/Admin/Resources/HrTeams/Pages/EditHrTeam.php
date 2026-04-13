<?php

namespace App\Filament\Admin\Resources\HrTeams\Pages;

use App\Filament\Admin\Resources\HrTeams\HrTeamResource;
use App\Models\Team;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Team\TeamService;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditHrTeam extends EditRecord
{
    protected static string $resource = HrTeamResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        abort_unless($record instanceof Team, 404);

        return app(TeamService::class)->updateTeam($record->id, $data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $actor = auth()->user();

        if (! $record instanceof Team || ! $actor instanceof User) {
            return;
        }

        app(GovernanceAuditLogger::class)->log('governance.hr.team.updated', $record, [
            'after' => [
                'name' => $record->name,
                'code' => $record->code,
            ],
        ], $actor);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteTeam')
                ->label('Delete')
                ->requiresConfirmation()
                ->color('danger')
                ->action(function (): void {
                    $record = $this->getRecord();

                    abort_unless($record instanceof Team, 404);

                    $actor = auth()->user();

                    abort_unless($actor instanceof User, 403);

                    app(GovernanceAuditLogger::class)->log('governance.hr.team.deleted', $record, [
                        'before' => [
                            'name' => $record->name,
                            'code' => $record->code,
                        ],
                    ], $actor);

                    app(TeamService::class)->deleteTeam($record->id);

                    $this->redirect(HrTeamResource::getUrl());
                }),
            RestoreAction::make(),
        ];
    }
}
