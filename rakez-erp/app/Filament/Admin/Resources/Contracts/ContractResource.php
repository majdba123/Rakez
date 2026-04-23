<?php

namespace App\Filament\Admin\Resources\Contracts;

use App\Enums\ContractWorkflowStatus;
use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\Contracts\Pages\ListContracts;
use App\Filament\Admin\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\GovernanceAuditLog;
use App\Models\SecondPartyData;
use App\Models\User;
use App\Services\Contract\ContractService;
use App\Support\Filament\ProcessStepper;
use App\Support\Projects\ContractReadinessStepBuilder;
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
use Illuminate\Support\HtmlString;

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
                    ->options(static::contractStatusOptions()),
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

                        app(ContractService::class)->transitionStatusForGovernance(
                            $record->id,
                            'approved',
                            'governance.contracts.approved',
                            $actor,
                        );

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

                        app(ContractService::class)->transitionStatusForGovernance(
                            $record->id,
                            'rejected',
                            'governance.contracts.rejected',
                            $actor,
                        );

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
                    ->visible(fn (Contract $record): bool => static::canGovernanceMutation('contracts.approve') && $record->isApprovedOrCompleted())
                    ->action(function (Contract $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        app(ContractService::class)->transitionStatusForGovernance(
                            $record->id,
                            'ready',
                            'governance.contracts.marked_ready',
                            $actor,
                            true,
                        );

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
            Section::make('Project Tracker')
                ->schema([
                    TextEntry::make('project_tracker')
                        ->state(fn (Contract $record): HtmlString => new HtmlString(
                            ProcessStepper::render(app(ContractReadinessStepBuilder::class)->stepsForContract($record))
                        ))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make('Readiness')
                ->schema([
                    TextEntry::make('readiness_status')
                        ->label('Readiness Status')
                        ->state(fn (Contract $record): string => $record->checkMarketingReadiness()['ready'] ? 'Ready' : 'Action Required')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Ready' ? 'success' : 'warning'),
                    IconEntry::make('readiness_has_contract_info')
                        ->label('Has Contract Info')
                        ->state(fn (Contract $record): bool => (bool) $record->info)
                        ->boolean(),
                    IconEntry::make('readiness_second_party_complete')
                        ->label('Second Party Complete')
                        ->state(fn (Contract $record): bool => SecondPartyData::hasAllCompletionFieldsFilled($record->secondPartyData))
                        ->boolean(),
                    TextEntry::make('readiness_units_uploaded')
                        ->label('Units Uploaded')
                        ->state(fn (Contract $record): string => (string) $record->contractUnits()->count()),
                    IconEntry::make('readiness_boards_processed')
                        ->label('Boards Processed')
                        ->state(fn (Contract $record): bool => (bool) $record->boardsDepartment?->processed_at)
                        ->boolean(),
                    TextEntry::make('readiness_photography_status')
                        ->label('Photography')
                        ->state(fn (Contract $record): string => ucfirst((string) ($record->photographyDepartment?->status ?? 'pending')))
                        ->badge(),
                    TextEntry::make('readiness_montage_status')
                        ->label('Montage')
                        ->state(fn (Contract $record): string => ucfirst((string) ($record->montageDepartment?->status ?? 'pending')))
                        ->badge(),
                    TextEntry::make('readiness_missing')
                        ->label('Missing Requirements')
                        ->state(fn (Contract $record): HtmlString => static::missingReadinessList($record))
                        ->html()
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('Audit / History')
                ->schema([
                    TextEntry::make('governance_audit_history')
                        ->label('Recent Governance Activity')
                        ->state(fn (Contract $record): HtmlString => static::governanceAuditList($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
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

    private static function contractStatusOptions(): array
    {
        return ContractWorkflowStatus::options();
    }

    private static function missingReadinessList(Contract $record): HtmlString
    {
        $missing = $record->checkMarketingReadiness()['missing'];

        if ($missing === []) {
            return new HtmlString('<p>All readiness requirements are complete.</p>');
        }

        $items = collect($missing)
            ->map(fn (string $item): string => '<li>' . e($item) . '</li>')
            ->implode('');

        return new HtmlString("<ul class=\"list-disc ps-5 space-y-1\">{$items}</ul>");
    }

    private static function governanceAuditList(Contract $record): HtmlString
    {
        $entries = GovernanceAuditLog::query()
            ->where('subject_type', Contract::class)
            ->where('subject_id', $record->id)
            ->latest()
            ->limit(10)
            ->get();

        $actorNames = User::query()
            ->whereIn('id', $entries->pluck('actor_id')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        if ($entries->isEmpty()) {
            return new HtmlString('<p>No governance activity recorded for this contract yet.</p>');
        }

        $items = $entries
            ->map(function (GovernanceAuditLog $entry) use ($actorNames): string {
                $timestamp = $entry->created_at?->format('Y-m-d H:i') ?? '-';
                $actor = $entry->actor_id ? ($actorNames->get($entry->actor_id) ?? ('User #' . $entry->actor_id)) : 'System';

                return sprintf(
                    '<li><strong>%s</strong> by %s <span class="text-gray-500">(%s)</span></li>',
                    e($entry->event),
                    e($actor),
                    e($timestamp),
                );
            })
            ->implode('');

        return new HtmlString("<ul class=\"list-disc ps-5 space-y-1\">{$items}</ul>");
    }
}
