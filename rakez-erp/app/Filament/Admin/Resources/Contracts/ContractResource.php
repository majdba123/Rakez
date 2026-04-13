<?php

namespace App\Filament\Admin\Resources\Contracts;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\Contracts\Pages\ListContracts;
use App\Filament\Admin\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\User;
use App\Services\Contract\ContractService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContractResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = Contract::class;

    protected static string $viewPermission = 'contracts.view_all';

    protected static ?string $slug = 'contracts';

    protected static ?string $navigationLabel = 'Contracts';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string | \UnitEnum | null $navigationGroup = 'Contracts & Projects';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['city', 'district', 'user'])
            ->withCount(['contractUnits', 'teams']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('code')->searchable()->copyable(),
                TextColumn::make('project_name')->label('Project')->searchable(),
                TextColumn::make('developer_name')->label('Developer')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('contract_type')->label('Type')->badge()->placeholder('-'),
                TextColumn::make('city.name')->label('City')->placeholder('-'),
                TextColumn::make('contract_units_count')->label('Units')->sortable(),
                TextColumn::make('teams_count')->label('Teams')->sortable(),
                IconColumn::make('is_off_plan')->label('Off Plan')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('contract_type')
                    ->options([
                        'exclusive' => 'Exclusive',
                        'marketing' => 'Marketing',
                        'standard' => 'Standard',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('approveContract')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record): bool => static::canGovernanceMutation('contracts.approve') && $record->isPending())
                    ->action(function (Contract $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(ContractService::class)->updateContractStatus($record->id, 'approved');

                        app(GovernanceAuditLogger::class)->log('governance.contracts.approved', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Contract approved.')
                            ->send();
                    }),
                Action::make('rejectContract')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record): bool => static::canGovernanceMutation('contracts.approve') && $record->isPending())
                    ->action(function (Contract $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(ContractService::class)->updateContractStatus($record->id, 'rejected');

                        app(GovernanceAuditLogger::class)->log('governance.contracts.rejected', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Contract rejected.')
                            ->send();
                    }),
                Action::make('markReadyForMarketing')
                    ->label('Mark Ready')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record): bool => static::canGovernanceMutation('contracts.approve') && $record->isApproved())
                    ->action(function (Contract $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(ContractService::class)->updateContractStatusByProjectManagement($record->id, 'ready');

                        app(GovernanceAuditLogger::class)->log('governance.contracts.marked_ready', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Contract marked ready for marketing.')
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contract')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('code')->copyable(),
                    TextEntry::make('project_name')->label('Project'),
                    TextEntry::make('developer_name')->label('Developer'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('contract_type')->label('Type')->badge()->placeholder('-'),
                    IconEntry::make('is_off_plan')->label('Off Plan')->boolean(),
                    TextEntry::make('city.name')->label('City')->placeholder('-'),
                    TextEntry::make('district.name')->label('District')->placeholder('-'),
                    TextEntry::make('user.name')->label('Submitted By')->placeholder('-'),
                    TextEntry::make('contract_units_count')->label('Units'),
                    TextEntry::make('teams_count')->label('Teams'),
                    TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Contracts & Projects', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Contracts & Projects', static::$viewPermission);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
            'view' => ViewContract::route('/{record}'),
        ];
    }
}
