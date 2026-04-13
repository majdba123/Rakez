<?php

namespace App\Filament\Admin\Resources\WorkflowTasks\Pages;

use App\Filament\Admin\Resources\WorkflowTasks\WorkflowTaskResource;
use App\Models\Team;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Workflow\WorkflowTaskAdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowTasks extends ListRecords
{
    protected static string $resource = WorkflowTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTask')
                ->label('Create Task')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => WorkflowTaskResource::canManageTasks())
                ->schema([
                    TextInput::make('task_name')
                        ->label('Task')
                        ->required()
                        ->maxLength(255),
                    Select::make('assigned_to')
                        ->label('Assignee')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('team_id')
                        ->label('Team')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => Team::query()->orderBy('name')->pluck('name', 'id')->all()),
                    TextInput::make('section')
                        ->maxLength(100)
                        ->helperText('Optional. Defaults to the assignee user type.'),
                    DateTimePicker::make('due_at')
                        ->label('Due At'),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();

                    abort_unless($actor instanceof User, 403);

                    $task = app(WorkflowTaskAdminService::class)->create($data, $actor);

                    app(GovernanceAuditLogger::class)->log('governance.workflow.task.created', $task, [
                        'after' => [
                            'task_name' => $task->task_name,
                            'status' => $task->status,
                            'assigned_to' => $task->assigned_to,
                        ],
                    ], $actor);

                    Notification::make()
                        ->success()
                        ->title('Task created successfully.')
                        ->send();
                }),
        ];
    }
}
