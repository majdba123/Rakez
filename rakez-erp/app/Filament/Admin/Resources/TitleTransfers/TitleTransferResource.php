<?php

namespace App\Filament\Admin\Resources\TitleTransfers;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\TitleTransfers\Pages\ListTitleTransfers;
use App\Filament\Admin\Resources\TitleTransfers\Pages\ViewTitleTransfer;
use App\Models\TitleTransfer;
use App\Models\User;
use App\Support\Credit\CreditProcessStepBuilder;
use App\Support\Filament\ProcessStepper;
use App\Services\Credit\TitleTransferService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TitleTransferResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;

    protected static ?string $model = TitleTransfer::class;

    protected static ?string $slug = 'title-transfers';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Title Transfer Review';

    protected static string | \UnitEnum | null $navigationGroup = 'Credit Oversight';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['reservation.contract', 'reservation.contractUnit', 'processedBy'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('reservation.id')->label(__('filament-admin.resources.title_transfers.columns.booking'))->sortable(),
                TextColumn::make('project_name')
                    ->label(__('filament-admin.resources.title_transfers.columns.project'))
                    ->state(fn (TitleTransfer $record): string => $record->reservation?->contract?->project_name ?? '-'),
                TextColumn::make('reservation.contractUnit.unit_number')
                    ->label(__('filament-admin.resources.title_transfers.columns.unit'))
                    ->placeholder('-'),
                TextColumn::make('processedBy.name')
                    ->label(__('filament-admin.resources.title_transfers.columns.processed_by'))
                    ->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('scheduled_date')->date()->placeholder('-'),
                TextColumn::make('completed_date')->date()->placeholder('-'),
                TextColumn::make('created_at')->label(__('filament-admin.resources.title_transfers.columns.created'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'preparation' => __('filament-admin.resources.title_transfers.status.preparation'),
                        'scheduled' => __('filament-admin.resources.title_transfers.status.scheduled'),
                        'completed' => __('filament-admin.resources.title_transfers.status.completed'),
                    ]),
                SelectFilter::make('processed_by')
                    ->label(__('filament-admin.resources.title_transfers.columns.processed_by'))
                    ->relationship('processedBy', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('scheduleTransfer')
                    ->label(__('filament-admin.resources.title_transfers.actions.schedule'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('info')
                    ->schema([
                        DatePicker::make('scheduled_date')
                            ->required(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (TitleTransfer $record): bool => static::canGovernanceMutation('credit.title_transfer.manage') && ! $record->isCompleted())
                    ->action(function (TitleTransfer $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(TitleTransferService::class)->scheduleTransfer($record->id, (string) $data['scheduled_date'], $data['notes'] ?? null);

                        app(GovernanceAuditLogger::class)->log('governance.credit.title_transfer.scheduled', $updated, [
                            'before' => [
                                'status' => $record->status,
                                'scheduled_date' => optional($record->scheduled_date)?->toDateString(),
                            ],
                            'after' => [
                                'status' => $updated->status,
                                'scheduled_date' => optional($updated->scheduled_date)?->toDateString(),
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.title_transfers.notifications.scheduled'))
                            ->send();
                    }),
                Action::make('unscheduleTransfer')
                    ->label(__('filament-admin.resources.title_transfers.actions.clear_schedule'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (TitleTransfer $record): bool => static::canGovernanceMutation('credit.title_transfer.manage') && $record->isScheduled())
                    ->action(function (TitleTransfer $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(TitleTransferService::class)->unscheduleTransfer($record->id);

                        app(GovernanceAuditLogger::class)->log('governance.credit.title_transfer.unscheduled', $updated, [
                            'before' => [
                                'status' => $record->status,
                                'scheduled_date' => optional($record->scheduled_date)?->toDateString(),
                            ],
                            'after' => [
                                'status' => $updated->status,
                                'scheduled_date' => optional($updated->scheduled_date)?->toDateString(),
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.title_transfers.notifications.cleared'))
                            ->send();
                    }),
                Action::make('completeTransfer')
                    ->label(__('filament-admin.resources.title_transfers.actions.complete'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (TitleTransfer $record): bool => static::canGovernanceMutation('credit.title_transfer.manage') && ! $record->isCompleted())
                    ->action(function (TitleTransfer $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(TitleTransferService::class)->completeTransfer($record->id, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.credit.title_transfer.completed', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => [
                                'status' => $updated->status,
                                'completed_date' => optional($updated->completed_date)?->toDateString(),
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.title_transfers.notifications.completed'))
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament-admin.resources.title_transfers.sections.stepper'))
                ->schema([
                    TextEntry::make('transfer_stepper')
                        ->label(__('filament-admin.resources.title_transfers.stepper.title'))
                        ->state(fn (TitleTransfer $record) => static::transferStepper($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make(__('filament-admin.resources.title_transfers.sections.review'))
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('scheduled_date')->date()->placeholder('-'),
                    TextEntry::make('completed_date')->date()->placeholder('-'),
                    TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('filament-admin.resources.title_transfers.sections.reservation'))
                ->schema([
                    TextEntry::make('reservation.id')->label(__('filament-admin.resources.title_transfers.entries.booking_id')),
                    TextEntry::make('reservation.client_name')->label(__('filament-admin.resources.title_transfers.entries.client'))->placeholder('-'),
                    TextEntry::make('reservation.contract.project_name')->label(__('filament-admin.resources.title_transfers.columns.project'))->placeholder('-'),
                    TextEntry::make('reservation.contractUnit.unit_number')->label(__('filament-admin.resources.title_transfers.columns.unit'))->placeholder('-'),
                    TextEntry::make('processedBy.name')->label(__('filament-admin.resources.title_transfers.columns.processed_by'))->placeholder('-'),
                    TextEntry::make('reservation.credit_status')->label(__('filament-admin.resources.title_transfers.entries.credit_status'))->badge(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTitleTransfers::route('/'),
            'view' => ViewTitleTransfer::route('/{record}'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-admin.resources.title_transfers.navigation_label');
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Credit Oversight', 'credit.bookings.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Credit Oversight', 'credit.bookings.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    protected static function transferStepper(TitleTransfer $record): \Illuminate\Support\HtmlString
    {
        $steps = collect(app(CreditProcessStepBuilder::class)->titleTransferSteps($record))
            ->map(function (array $step): array {
                $label = match ($step['key']) {
                    'preparation' => __('filament-admin.resources.title_transfers.stepper.steps.preparation'),
                    'scheduled' => __('filament-admin.resources.title_transfers.stepper.steps.scheduled'),
                    'completed' => __('filament-admin.resources.title_transfers.stepper.steps.completed'),
                    'not_started' => __('filament-admin.resources.credit_bookings.stepper.transfer_not_started'),
                    default => $step['key'],
                };

                return [
                    'label' => $label,
                    'state' => $step['state'],
                ];
            })
            ->values()
            ->all();

        return ProcessStepper::render($steps);
    }
}
