<?php

namespace App\Filament\Admin\Resources\ExclusiveProjectRequests;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\ExclusiveProjectRequests\Pages\ListExclusiveProjectRequests;
use App\Filament\Admin\Resources\ExclusiveProjectRequests\Pages\ViewExclusiveProjectRequest;
use App\Models\ExclusiveProjectRequest;
use App\Models\User;
use App\Services\ExclusiveProjectService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExclusiveProjectRequestResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = ExclusiveProjectRequest::class;

    protected static string $viewPermission = 'exclusive_projects.view';

    protected static ?string $slug = 'exclusive-project-requests';

    protected static ?string $navigationLabel = 'Exclusive Requests';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedStar;

    protected static string | \UnitEnum | null $navigationGroup = 'Contracts & Projects';

    protected static ?int $navigationSort = 11;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['requestedBy', 'approvedBy', 'contract']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('project_name')->label('Project')->searchable(),
                TextColumn::make('developer_name')->label('Developer')->searchable(),
                TextColumn::make('requestedBy.name')->label('Requested By')->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('approvedBy.name')->label('Approved By')->placeholder('-'),
                TextColumn::make('contract.code')->label('Contract')->placeholder('-'),
                TextColumn::make('approved_at')->dateTime()->placeholder('-'),
                TextColumn::make('created_at')->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'contract_completed' => 'Contract Completed',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('approveRequest')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ExclusiveProjectRequest $record): bool => static::canGovernanceMutation('exclusive_projects.approve') && $record->isPending())
                    ->action(function (ExclusiveProjectRequest $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(ExclusiveProjectService::class)->approveRequest($record->id, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.projects.exclusive_request.approved', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Exclusive request approved.')
                            ->send();
                    }),
                Action::make('rejectRequest')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(4)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (ExclusiveProjectRequest $record): bool => static::canGovernanceMutation('exclusive_projects.approve') && $record->isPending())
                    ->action(function (ExclusiveProjectRequest $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(ExclusiveProjectService::class)->rejectRequest($record->id, (string) $data['reason'], $actor);

                        app(GovernanceAuditLogger::class)->log('governance.projects.exclusive_request.rejected', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => [
                                'status' => $updated->status,
                                'rejection_reason' => $updated->rejection_reason,
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Exclusive request rejected.')
                            ->send();
                    }),
                Action::make('completeContract')
                    ->label('Complete Contract')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->color('info')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(1000),
                        Repeater::make('units')
                            ->schema([
                                TextInput::make('type')->required()->maxLength(255),
                                TextInput::make('count')->required()->numeric()->minValue(1),
                                TextInput::make('price')->required()->numeric()->minValue(0),
                            ])
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (ExclusiveProjectRequest $record): bool => static::canGovernanceMutation('exclusive_projects.contract.complete') && $record->isApproved())
                    ->action(function (ExclusiveProjectRequest $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(ExclusiveProjectService::class)->completeContract($record->id, $data, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.projects.exclusive_request.contract_completed', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => [
                                'status' => $updated->status,
                                'contract_id' => $updated->contract_id,
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Exclusive project contract completed.')
                            ->send();
                    }),
                Action::make('exportContract')
                    ->label('Generate PDF')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (ExclusiveProjectRequest $record): bool => static::canGovernanceMutation('exclusive_projects.contract.export') && $record->isContractCompleted())
                    ->action(function (ExclusiveProjectRequest $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $path = app(ExclusiveProjectService::class)->exportContract($record->id);

                        app(GovernanceAuditLogger::class)->log('governance.projects.exclusive_request.contract_exported', $record->fresh(), [
                            'pdf_path' => $path,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Exclusive contract PDF generated.')
                            ->body($path)
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('project_name')->label('Project'),
                    TextEntry::make('developer_name')->label('Developer'),
                    TextEntry::make('developer_contact')->label('Contact')->placeholder('-'),
                    TextEntry::make('estimated_units')->label('Est. Units')->placeholder('-'),
                    TextEntry::make('location_city')->label('City')->placeholder('-'),
                    TextEntry::make('location_district')->label('District')->placeholder('-'),
                    TextEntry::make('requestedBy.name')->label('Requested By')->placeholder('-'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('approvedBy.name')->label('Approved By')->placeholder('-'),
                    TextEntry::make('approved_at')->dateTime()->placeholder('-'),
                    TextEntry::make('rejection_reason')->placeholder('-')->columnSpanFull(),
                    TextEntry::make('contract.code')->label('Linked Contract')->placeholder('-'),
                    TextEntry::make('contract_completed_at')->dateTime()->placeholder('-'),
                    TextEntry::make('contract_pdf_path')->label('Contract PDF')->placeholder('-')->copyable(),
                    TextEntry::make('project_description')
                        ->label('Description')
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('created_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExclusiveProjectRequests::route('/'),
            'view' => ViewExclusiveProjectRequest::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Contracts & Projects', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Contracts & Projects', static::$viewPermission);
    }
}
