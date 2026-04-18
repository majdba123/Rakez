<?php

namespace App\Filament\Admin\Resources\MarketingTasks;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\MarketingTasks\Pages\CreateMarketingTask;
use App\Filament\Admin\Resources\MarketingTasks\Pages\EditMarketingTask;
use App\Filament\Admin\Resources\MarketingTasks\Pages\ListMarketingTasks;
use App\Models\Contract;
use App\Models\MarketingTask;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Marketing\MarketingTaskService;
use App\Support\Governance\GovernanceStatusCatalog;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketingTaskResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = MarketingTask::class;

    protected static string $viewPermission = 'marketing.tasks.view';

    protected static ?string $slug = 'marketing-tasks-admin';

    protected static ?string $navigationLabel = 'Marketing Tasks';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboard;

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing Oversight';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('contract_id')
                ->label('Project')
                ->searchable()
                ->preload()
                ->options(fn (): array => Contract::query()->orderBy('project_name')->pluck('project_name', 'id')->all()),
            TextInput::make('task_name')
                ->label('Task')
                ->required()
                ->maxLength(255),
            Select::make('marketer_id')
                ->label('Marketer')
                ->required()
                ->searchable()
                ->preload()
                ->options(fn (): array => User::query()->where('type', 'marketing')->orderBy('name')->pluck('name', 'id')->all()),
            TextInput::make('participating_marketers_count')
                ->numeric()
                ->default(1),
            TextInput::make('design_link')
                ->url()
                ->maxLength(255),
            TextInput::make('design_number')
                ->maxLength(255),
            TextInput::make('design_description')
                ->maxLength(255),
            Select::make('status')
                ->required()
                ->default('pending')
                ->options(GovernanceStatusCatalog::marketingTaskStatusOptions()),
            DatePicker::make('due_date'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contract', 'marketer', 'creator'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('task_name')->label('Task')->searchable(),
                TextColumn::make('marketer.name')->label('Marketer')->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('due_date')->date()->placeholder('-'),
                TextColumn::make('participating_marketers_count')->label('Participants')->numeric(),
                TextColumn::make('creator.name')->label('Created By')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(GovernanceStatusCatalog::marketingTaskStatusOptions()),
            ])
            ->actions([
                EditAction::make(),
                Action::make('deleteTask')
                    ->label(__('filament-admin.resources.marketing_tasks.actions.delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MarketingTask $record): bool => static::canDelete($record))
                    ->action(function (MarketingTask $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        app(GovernanceAuditLogger::class)->log('governance.marketing.task.deleted', $record, [
                            'before' => [
                                'status' => $record->status,
                                'task_name' => $record->task_name,
                            ],
                        ], $actor);

                        app(MarketingTaskService::class)->deleteTask($record->id);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.marketing_tasks.notifications.deleted'))
                            ->send();
                    }),
                Action::make('markCompleted')
                    ->label(__('filament-admin.resources.marketing_tasks.actions.mark_completed'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (MarketingTask $record): bool => static::canGovernanceMutation('marketing.tasks.confirm') && $record->status !== 'completed')
                    ->action(function (MarketingTask $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        $updated = app(MarketingTaskService::class)->updateTaskStatus($record->id, 'completed');

                        app(GovernanceAuditLogger::class)->log('governance.marketing.task.completed', $updated, [
                            'before' => ['status' => $beforeStatus],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.marketing_tasks.notifications.completed'))
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingTasks::route('/'),
            'create' => CreateMarketingTask::route('/create'),
            'edit' => EditMarketingTask::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Marketing Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Marketing Oversight', static::$viewPermission);
    }

    protected static function createPermission(): ?string
    {
        return 'marketing.tasks.confirm';
    }

    protected static function editPermission(): ?string
    {
        return 'marketing.tasks.confirm';
    }

    protected static function deletePermission(): ?string
    {
        return 'marketing.tasks.confirm';
    }
}
