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
                TextColumn::make('task_name')->label(__('filament-admin.resources.workflow_tasks.columns.task'))->searchable(),
                TextColumn::make('section')->badge()->placeholder('-'),
                TextColumn::make('team.name')->label(__('filament-admin.resources.workflow_tasks.columns.team'))->placeholder('-'),
                TextColumn::make('assignee.name')->label(__('filament-admin.resources.workflow_tasks.columns.assignee'))->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('due_at')->dateTime()->placeholder('-'),
                TextColumn::make('creator.name')->label(__('filament-admin.resources.workflow_tasks.columns.created_by'))->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Task::STATUS_IN_PROGRESS => __('filament-admin.resources.workflow_tasks.status.in_progress'),
                        Task::STATUS_COMPLETED => __('filament-admin.resources.workflow_tasks.status.completed'),
                        Task::STATUS_COULD_NOT_COMPLETE => __('filament-admin.resources.workflow_tasks.status.could_not_complete'),
                    ]),
            ])
            ->actions([
                Action::make('markInProgress')
                    ->label(__('filament-admin.resources.workflow_tasks.actions.mark_in_progress'))
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
                            ->title(__('filament-admin.resources.workflow_tasks.notifications.moved_in_progress'))
                            ->send();
                    }),
                Action::make('markCompleted')
                    ->label(__('filament-admin.resources.workflow_tasks.actions.mark_completed'))
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
                            ->title(__('filament-admin.resources.workflow_tasks.notifications.marked_completed'))
                            ->send();
                    }),
                Action::make('markCouldNotComplete')
                    ->label(__('filament-admin.resources.workflow_tasks.actions.could_not_complete'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning')
                    ->schema([
                        Textarea::make('cannot_complete_reason')
                            ->label(__('filament-admin.resources.workflow_tasks.fields.reason'))
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
                            ->title(__('filament-admin.resources.workflow_tasks.notifications.marked_not_completable'))
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

    public static function getNavigationLabel(): string
    {
        return __('filament-admin.resources.workflow_tasks.navigation_label');
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
