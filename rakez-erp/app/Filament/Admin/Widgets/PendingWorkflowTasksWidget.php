<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\Task;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PendingWorkflowTasksWidget extends TableWidget
{
    use HasGovernanceAuthorization;

    protected static ?string $heading = 'Workflow Queue';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Requests & Workflow', 'governance.approvals.center.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Task::query()
                    ->with(['team', 'assignee'])
                    ->where('status', Task::STATUS_IN_PROGRESS)
                    ->latest('due_at')
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('task_name')->label('Task')->searchable(),
                TextColumn::make('section')->badge()->placeholder('-'),
                TextColumn::make('team.name')->label('Team')->placeholder('-'),
                TextColumn::make('assignee.name')->label('Assignee')->placeholder('-'),
                TextColumn::make('due_at')->label('Due')->dateTime()->placeholder('-'),
            ])
            ->paginated(false);
    }
}
