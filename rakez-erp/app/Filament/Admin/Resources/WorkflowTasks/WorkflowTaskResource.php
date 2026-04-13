<?php

namespace App\Filament\Admin\Resources\WorkflowTasks;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\WorkflowTasks\Pages\ListWorkflowTasks;
use App\Models\Task;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Workflow\WorkflowTaskAdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkflowTaskResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = Task::class;

    protected static string $viewPermission = 'governance.oversight.workflow.view';

    protected static ?string $slug = 'workflow-tasks';

    protected static ?string $navigationLabel = 'Workflow Tasks';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string | \UnitEnum | null $navigationGroup = 'Requests & Workflow';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['team', 'assignee', 'creator'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('task_name')->label('Task')->searchable(),
                TextColumn::make('section')->badge()->placeholder('-'),
                TextColumn::make('team.name')->label('Team')->placeholder('-'),
                TextColumn::make('assignee.name')->label('Assignee')->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('due_at')->dateTime()->placeholder('-'),
                TextColumn::make('creator.name')->label('Created By')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Task::STATUS_IN_PROGRESS => 'In Progress',
                        Task::STATUS_COMPLETED => 'Completed',
                        Task::STATUS_COULD_NOT_COMPLETE => 'Could Not Complete',
                    ]),
            ])
            ->actions([
                Action::make('markInProgress')
                    ->label('Mark In Progress')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->visible(fn (Task $record): bool => static::canGovernanceMutation('tasks.create') && $record->status !== Task::STATUS_IN_PROGRESS)
                    ->action(function (Task $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        $updated = app(WorkflowTaskAdminService::class)->updateStatus($record, [
                            'status' => Task::STATUS_IN_PROGRESS,
                        ]);

                        app(GovernanceAuditLogger::class)->log('governance.workflow.task.status_updated', $updated, [
                            'before' => ['status' => $beforeStatus],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Task moved back to in progress.')
                            ->send();
                    }),
                Action::make('markCompleted')
                    ->label('Mark Completed')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Task $record): bool => static::canGovernanceMutation('tasks.create') && $record->status !== Task::STATUS_COMPLETED)
                    ->action(function (Task $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        $updated = app(WorkflowTaskAdminService::class)->updateStatus($record, [
                            'status' => Task::STATUS_COMPLETED,
                        ]);

                        app(GovernanceAuditLogger::class)->log('governance.workflow.task.status_updated', $updated, [
                            'before' => ['status' => $beforeStatus],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Task marked as completed.')
                            ->send();
                    }),
                Action::make('markCouldNotComplete')
                    ->label('Could Not Complete')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning')
                    ->schema([
                        Textarea::make('cannot_complete_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(4)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Task $record): bool => static::canGovernanceMutation('tasks.create') && $record->status !== Task::STATUS_COULD_NOT_COMPLETE)
                    ->action(function (Task $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        $updated = app(WorkflowTaskAdminService::class)->updateStatus($record, [
                            'status' => Task::STATUS_COULD_NOT_COMPLETE,
                            'cannot_complete_reason' => $data['cannot_complete_reason'] ?? null,
                        ]);

                        app(GovernanceAuditLogger::class)->log('governance.workflow.task.status_updated', $updated, [
                            'before' => ['status' => $beforeStatus],
                            'after' => [
                                'status' => $updated->status,
                                'cannot_complete_reason' => $updated->cannot_complete_reason,
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Task marked as not completable.')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowTasks::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Requests & Workflow', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Requests & Workflow', static::$viewPermission);
    }

    public static function canManageTasks(): bool
    {
        return static::canGovernanceMutation('tasks.create');
    }
}
