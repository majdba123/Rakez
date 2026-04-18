<?php

namespace App\Filament\Admin\Resources\CreditBookings;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\CreditBookings\Pages\ListCreditBookings;
use App\Filament\Admin\Resources\CreditBookings\Pages\ViewCreditBooking;
use App\Models\SalesReservation;
use App\Models\User;
use App\Support\Credit\CreditProcessStepBuilder;
use App\Support\Filament\ProcessStepper;
use App\Services\Credit\ClaimFileService;
use App\Services\Credit\CreditFinancingService;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Sales\SalesReservationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CreditBookingResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;

    protected static ?string $model = SalesReservation::class;

    protected static ?string $slug = 'credit-bookings';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Booking Review';

    protected static string | \UnitEnum | null $navigationGroup = 'Credit Oversight';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::baseQuery($query))
            ->columns([
                TextColumn::make('id')->sortable()->searchable(),
                TextColumn::make('project_name')
                    ->label(__('filament-admin.resources.credit_bookings.columns.project'))
                    ->state(fn (SalesReservation $record): string => static::projectName($record))
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('contract', function (Builder $contractQuery) use ($search): void {
                                $contractQuery
                                    ->where('project_name', 'like', "%{$search}%")
                                    ->orWhereHas('info', fn (Builder $infoQuery): Builder => $infoQuery->where('project_name', 'like', "%{$search}%"));
                            });
                        },
                    ),
                TextColumn::make('contractUnit.unit_number')
                    ->label(__('filament-admin.resources.credit_bookings.columns.unit'))
                    ->placeholder('-'),
                TextColumn::make('client_name')
                    ->label(__('filament-admin.resources.credit_bookings.columns.client'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('credit_status')->label(__('filament-admin.resources.credit_bookings.columns.credit_status'))->badge(),
                TextColumn::make('purchase_mechanism')
                    ->label(__('filament-admin.resources.credit_bookings.columns.purchase'))
                    ->state(fn (SalesReservation $record): string => static::purchaseMechanismLabel($record->purchase_mechanism)),
                IconColumn::make('down_payment_confirmed')
                    ->label(__('filament-admin.resources.credit_bookings.columns.deposit_confirmed'))
                    ->boolean(),
                TextColumn::make('financingTracker.overall_status')
                    ->label(__('filament-admin.resources.credit_bookings.columns.financing'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('titleTransfer.status')
                    ->label(__('filament-admin.resources.credit_bookings.columns.title_transfer'))
                    ->badge()
                    ->placeholder('-'),
                IconColumn::make('has_claim_file')
                    ->label(__('filament-admin.resources.credit_bookings.columns.claim_file'))
                    ->boolean()
                    ->state(fn (SalesReservation $record): bool => $record->claimFile !== null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'confirmed' => __('filament-admin.resources.credit_bookings.status.confirmed'),
                        'under_negotiation' => __('filament-admin.resources.credit_bookings.status.under_negotiation'),
                        'cancelled' => __('filament-admin.resources.credit_bookings.status.cancelled'),
                    ]),
                SelectFilter::make('credit_status')
                    ->options([
                        'pending' => __('filament-admin.resources.credit_bookings.credit_status.pending'),
                        'in_progress' => __('filament-admin.resources.credit_bookings.credit_status.in_progress'),
                        'title_transfer' => __('filament-admin.resources.credit_bookings.credit_status.title_transfer'),
                        'sold' => __('filament-admin.resources.credit_bookings.credit_status.sold'),
                        'rejected' => __('filament-admin.resources.credit_bookings.credit_status.rejected'),
                    ]),
                SelectFilter::make('purchase_mechanism')
                    ->options([
                        'cash' => __('filament-admin.resources.credit_bookings.purchase.cash'),
                        'supported_bank' => __('filament-admin.resources.credit_bookings.purchase.supported_bank'),
                        'unsupported_bank' => __('filament-admin.resources.credit_bookings.purchase.unsupported_bank'),
                    ]),
                SelectFilter::make('financing_status')
                    ->label(__('filament-admin.resources.credit_bookings.filters.financing_status'))
                    ->options([
                        'in_progress' => __('filament-admin.resources.credit_bookings.credit_status.in_progress'),
                        'completed' => __('filament-admin.resources.credit_bookings.financing_status.completed'),
                        'rejected' => __('filament-admin.resources.credit_bookings.credit_status.rejected'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->whereHas('financingTracker', fn (Builder $q) => $q->where('overall_status', $data['value']))
                        : $query),
                TernaryFilter::make('has_title_transfer')
                    ->label(__('filament-admin.resources.credit_bookings.filters.has_title_transfer'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('titleTransfer'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('titleTransfer'),
                    ),
                TernaryFilter::make('has_claim_file')
                    ->label(__('filament-admin.resources.credit_bookings.filters.has_claim_file'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('claimFile'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('claimFile'),
                    ),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('editClient')
                    ->label(__('filament-admin.resources.credit_bookings.actions.edit_client'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('credit.bookings.manage') && $record->status !== 'cancelled')
                    ->fillForm(fn (SalesReservation $record): array => [
                        'client_name' => $record->client_name,
                        'client_mobile' => $record->client_mobile,
                        'client_nationality' => $record->client_nationality,
                        'client_iban' => $record->client_iban,
                    ])
                    ->form([
                        TextInput::make('client_name')->required()->maxLength(255),
                        TextInput::make('client_mobile')->required()->maxLength(255),
                        TextInput::make('client_nationality')->maxLength(255),
                        TextInput::make('client_iban')->maxLength(255),
                    ])
                    ->action(function (SalesReservation $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $before = [
                            'client_name' => $record->client_name,
                            'client_mobile' => $record->client_mobile,
                            'client_nationality' => $record->client_nationality,
                            'client_iban' => $record->client_iban,
                        ];

                        $updated = app(SalesReservationService::class)->updateClientDetailsForCredit($record->id, $data, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.credit.booking.client_details_updated', $updated, [
                            'before' => $before,
                            'after' => [
                                'client_name' => $updated->client_name,
                                'client_mobile' => $updated->client_mobile,
                                'client_nationality' => $updated->client_nationality,
                                'client_iban' => $updated->client_iban,
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.credit_bookings.notifications.client_updated'))
                            ->send();
                    }),
                Action::make('logClientContact')
                    ->label(__('filament-admin.resources.credit_bookings.actions.log_contact'))
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('credit.bookings.manage') && $record->status !== 'cancelled')
                    ->form([
                        Textarea::make('notes')
                            ->required()
                            ->rows(4)
                            ->maxLength(1000),
                    ])
                    ->action(function (SalesReservation $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $log = app(SalesReservationService::class)->logCreditClientContact($record->id, (string) $data['notes'], $actor);

                        app(GovernanceAuditLogger::class)->log('governance.credit.booking.client_contact_logged', $record->fresh(), [
                            'sales_reservation_action_id' => $log->id,
                            'notes' => (string) $data['notes'],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.credit_bookings.notifications.contact_logged'))
                            ->send();
                    }),
                Action::make('cancelBooking')
                    ->label(__('filament-admin.resources.credit_bookings.actions.cancel'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('credit.bookings.manage') && $record->status !== 'cancelled')
                    ->form([
                        Textarea::make('cancellation_reason')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(function (SalesReservation $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        $updated = app(SalesReservationService::class)->cancelReservation($record->id, $data['cancellation_reason'] ?? null, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.credit.booking.cancelled', $updated, [
                            'before' => ['status' => $beforeStatus],
                            'after' => [
                                'status' => $updated->status,
                                'cancellation_reason' => $data['cancellation_reason'] ?? null,
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.credit_bookings.notifications.cancelled'))
                            ->send();
                    }),
                Action::make('advanceFinancing')
                    ->label(__('filament-admin.resources.credit_bookings.actions.advance_financing'))
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('credit.financing.manage') && $record->isBankFinancing() && ! in_array($record->credit_status, ['sold', 'rejected'], true))
                    ->form([
                        TextInput::make('bank_name')->maxLength(100),
                        TextInput::make('client_salary')->numeric()->minValue(0),
                        Select::make('employment_type')->options([
                            'government' => __('filament-admin.resources.credit_bookings.employment.government'),
                            'private' => __('filament-admin.resources.credit_bookings.employment.private'),
                        ]),
                        TextInput::make('appraiser_name')->maxLength(255),
                    ])
                    ->action(function (SalesReservation $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        app(CreditFinancingService::class)->advanceOrInitialize($record->id, $data, $actor);

                        $record->refresh()->load('financingTracker');

                        app(GovernanceAuditLogger::class)->log('governance.credit.financing.advanced', $record, [
                            'form' => $data,
                            'financing_tracker_id' => $record->financingTracker?->id,
                            'overall_status' => $record->financingTracker?->overall_status,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.credit_bookings.notifications.financing_advanced'))
                            ->send();
                    }),
                Action::make('rejectFinancing')
                    ->label(__('filament-admin.resources.credit_bookings.actions.reject_financing'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('credit.financing.manage') && $record->financingTracker?->overall_status === 'in_progress')
                    ->form([
                        Textarea::make('reason')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (SalesReservation $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);
                        abort_if($record->financingTracker === null, 404);

                        $trackerId = $record->financingTracker->id;

                        app(CreditFinancingService::class)->rejectFinancing($record->financingTracker->id, (string) $data['reason'], $actor);

                        $record->refresh()->load('financingTracker');

                        app(GovernanceAuditLogger::class)->log('governance.credit.financing.rejected', $record, [
                            'financing_tracker_id' => $trackerId,
                            'reason' => (string) $data['reason'],
                            'overall_status' => $record->financingTracker?->overall_status,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.credit_bookings.notifications.financing_rejected'))
                            ->send();
                    }),
                Action::make('generateClaimFile')
                    ->label(__('filament-admin.resources.credit_bookings.actions.generate_claim_file'))
                    ->icon(Heroicon::OutlinedFolderPlus)
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('credit.claim_files.manage') && $record->credit_status === 'sold' && $record->claimFile === null)
                    ->action(function (SalesReservation $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $claimFile = app(ClaimFileService::class)->generateClaimFile($record->id, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.credit.claim_file.created', $claimFile, [
                            'sales_reservation_id' => $record->id,
                            'claim_file_id' => $claimFile->id,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.credit_bookings.notifications.claim_file_generated'))
                            ->send();
                    }),
                Action::make('generateClaimPdf')
                    ->label(__('filament-admin.resources.credit_bookings.actions.generate_claim_pdf'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('credit.claim_files.manage') && $record->credit_status === 'sold')
                    ->action(function (SalesReservation $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $claimFile = app(ClaimFileService::class)->ensurePdfForReservation($record->id, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.credit.claim_file.pdf_ensured', $claimFile, [
                            'sales_reservation_id' => $record->id,
                            'pdf_path' => $claimFile->pdf_path,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.credit_bookings.notifications.claim_pdf_ready'))
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament-admin.resources.credit_bookings.sections.process_progress'))
                ->schema([
                    TextEntry::make('reservation_process_stepper')
                        ->label(__('filament-admin.resources.credit_bookings.stepper.reservation_title'))
                        ->state(fn (SalesReservation $record) => static::reservationProcessStepper($record))
                        ->html()
                        ->columnSpanFull(),
                    TextEntry::make('financing_stepper')
                        ->label(__('filament-admin.resources.credit_bookings.stepper.financing_title'))
                        ->state(fn (SalesReservation $record) => static::financingStepper($record))
                        ->html()
                        ->columnSpanFull(),
                    TextEntry::make('title_transfer_stepper')
                        ->label(__('filament-admin.resources.credit_bookings.stepper.transfer_title'))
                        ->state(fn (SalesReservation $record) => static::titleTransferStepper($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make(__('filament-admin.resources.credit_bookings.sections.reservation'))
                ->schema([
                    TextEntry::make('id')->label(__('filament-admin.resources.credit_bookings.entries.booking_id')),
                    TextEntry::make('project_name')
                        ->label(__('filament-admin.resources.credit_bookings.columns.project'))
                        ->state(fn (SalesReservation $record): string => static::projectName($record)),
                    TextEntry::make('contractUnit.unit_number')->label(__('filament-admin.resources.credit_bookings.columns.unit'))->placeholder('-'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('credit_status')->label(__('filament-admin.resources.credit_bookings.columns.credit_status'))->badge(),
                    TextEntry::make('purchase_mechanism')
                        ->label(__('filament-admin.resources.credit_bookings.columns.purchase'))
                        ->state(fn (SalesReservation $record): string => static::purchaseMechanismLabel($record->purchase_mechanism)),
                    IconEntry::make('down_payment_confirmed')->label(__('filament-admin.resources.credit_bookings.columns.deposit_confirmed'))->boolean(),
                    TextEntry::make('confirmed_at')->label(__('filament-admin.resources.credit_bookings.entries.confirmed_at'))->dateTime()->placeholder('-'),
                ])
                ->columns(2),
            Section::make(__('filament-admin.resources.credit_bookings.sections.client_financial'))
                ->schema([
                    TextEntry::make('client_name')->label(__('filament-admin.resources.credit_bookings.columns.client'))->placeholder('-'),
                    TextEntry::make('client_mobile')->label(__('filament-admin.resources.credit_bookings.entries.mobile'))->placeholder('-'),
                    TextEntry::make('client_nationality')->label(__('filament-admin.resources.credit_bookings.entries.nationality'))->placeholder('-'),
                    TextEntry::make('client_iban')->label(__('filament-admin.resources.credit_bookings.entries.iban'))->placeholder('-'),
                    TextEntry::make('down_payment_amount')->label(__('filament-admin.resources.credit_bookings.entries.down_payment'))->money('AED')->placeholder('-'),
                    TextEntry::make('payment_installments_count')
                        ->label(__('filament-admin.resources.credit_bookings.entries.installments'))
                        ->state(fn (SalesReservation $record): string => (string) $record->paymentInstallments()->count()),
                    TextEntry::make('remaining_payment_plan')
                        ->label(__('filament-admin.resources.credit_bookings.entries.remaining_payment_plan'))
                        ->state(fn (SalesReservation $record): string => number_format($record->getPaymentPlanRemaining(), 2) . ' AED'),
                    IconEntry::make('requires_accounting_confirmation')
                        ->label(__('filament-admin.resources.credit_bookings.entries.needs_accounting_confirmation'))
                        ->state(fn (SalesReservation $record): bool => $record->requiresAccountingConfirmation())
                        ->boolean(),
                ])
                ->columns(2),
            Section::make(__('filament-admin.resources.credit_bookings.sections.financing'))
                ->schema([
                    TextEntry::make('financingTracker.overall_status')->label(__('filament-admin.resources.credit_bookings.entries.overall_status'))->badge()->default(__('filament-admin.resources.credit_bookings.entries.no_financing_tracker')),
                    TextEntry::make('financingTracker.assignedUser.name')->label(__('filament-admin.resources.credit_bookings.entries.assigned_to'))->placeholder('-'),
                    TextEntry::make('financingTracker.current_stage')
                        ->label(__('filament-admin.resources.credit_bookings.entries.current_stage'))
                        ->state(fn (SalesReservation $record): string => $record->financingTracker ? (string) $record->financingTracker->getCurrentStage() : '-'),
                    TextEntry::make('financingTracker.remaining_days')
                        ->label(__('filament-admin.resources.credit_bookings.entries.remaining_days'))
                        ->state(fn (SalesReservation $record): string => $record->financingTracker?->getRemainingDays() !== null ? (string) $record->financingTracker->getRemainingDays() : '-'),
                    KeyValueEntry::make('financing_progress')
                        ->label(__('filament-admin.resources.credit_bookings.entries.progress_summary'))
                        ->state(fn (SalesReservation $record): array => static::financingProgressState($record))
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('filament-admin.resources.credit_bookings.sections.transfer_claim'))
                ->schema([
                    TextEntry::make('titleTransfer.status')->label(__('filament-admin.resources.credit_bookings.entries.title_transfer_status'))->badge()->placeholder('-'),
                    TextEntry::make('titleTransfer.scheduled_date')->label(__('filament-admin.resources.credit_bookings.entries.scheduled_date'))->date()->placeholder('-'),
                    TextEntry::make('titleTransfer.completed_date')->label(__('filament-admin.resources.credit_bookings.entries.completed_date'))->date()->placeholder('-'),
                    TextEntry::make('claimFile.id')
                        ->label(__('filament-admin.resources.credit_bookings.columns.claim_file'))
                        ->state(fn (SalesReservation $record): string => $record->claimFile?->id ? '#' . $record->claimFile->id : __('filament-admin.resources.credit_bookings.entries.not_generated')),
                    IconEntry::make('claim_file_pdf')
                        ->label(__('filament-admin.resources.credit_bookings.entries.claim_pdf'))
                        ->state(fn (SalesReservation $record): bool => (bool) $record->claimFile?->hasPdf())
                        ->boolean(),
                    TextEntry::make('claimFile.total_claim_amount')->label(__('filament-admin.resources.credit_bookings.entries.claim_amount'))->money('AED')->placeholder('-'),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditBookings::route('/'),
            'view' => ViewCreditBooking::route('/{record}'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-admin.resources.credit_bookings.navigation_label');
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
        return static::canGovernanceMutation('credit.bookings.manage');
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

    protected static function baseQuery(Builder $query): Builder
    {
        return $query
            ->with([
                'contract',
                'contractUnit',
                'marketingEmployee.team',
                'financingTracker.assignedUser',
                'titleTransfer.processedBy',
                'claimFile',
            ])
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('status', ['confirmed', 'under_negotiation', 'cancelled'])
                    ->orWhereIn('credit_status', ['in_progress', 'title_transfer', 'sold', 'rejected']);
            })
            ->orderByRaw('COALESCE(confirmed_at, created_at) DESC');
    }

    protected static function projectName(SalesReservation $record): string
    {
        return $record->contract?->project_name
            ?? $record->contract?->info?->project_name
            ?? '-';
    }

    protected static function purchaseMechanismLabel(?string $value): string
    {
        return match ($value) {
            'cash' => __('filament-admin.resources.credit_bookings.purchase.cash'),
            'supported_bank' => __('filament-admin.resources.credit_bookings.purchase.supported_bank'),
            'unsupported_bank' => __('filament-admin.resources.credit_bookings.purchase.unsupported_bank'),
            default => $value ?? '-',
        };
    }

    protected static function financingProgressState(SalesReservation $record): array
    {
        $tracker = $record->financingTracker;

        if (! $tracker) {
            return [
                __('filament-admin.resources.credit_bookings.entries.overall_status') => __('filament-admin.resources.credit_bookings.entries.no_financing_tracker'),
            ];
        }

        $state = [];

        for ($i = 1; $i <= 5; $i++) {
            $status = $tracker->getStageStatus($i);
            $deadline = $tracker->getStageDeadline($i)?->format('Y-m-d H:i');

            $state[__('filament-admin.resources.credit_bookings.stage.label', ['number' => $i])] = $deadline
                ? __('filament-admin.resources.credit_bookings.stage.value_with_deadline', ['status' => $status, 'deadline' => $deadline])
                : $status;
        }

        return $state;
    }

    protected static function reservationProcessStepper(SalesReservation $record): \Illuminate\Support\HtmlString
    {
        $steps = collect(app(CreditProcessStepBuilder::class)->reservationLifecycleSteps($record))
            ->map(function (array $step): array {
                $label = match ($step['key']) {
                    'confirmed' => __('filament-admin.resources.credit_bookings.stepper.steps.confirmed'),
                    'financing' => __('filament-admin.resources.credit_bookings.stepper.steps.financing'),
                    'title_transfer' => __('filament-admin.resources.credit_bookings.stepper.steps.title_transfer'),
                    'sold' => __('filament-admin.resources.credit_bookings.stepper.steps.sold'),
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

    protected static function financingStepper(SalesReservation $record): \Illuminate\Support\HtmlString
    {
        $steps = collect(app(CreditProcessStepBuilder::class)->financingStepsForReservation($record))
            ->map(function (array $step): array {
                $label = match ($step['key']) {
                    'not_required' => __('filament-admin.resources.credit_bookings.stepper.financing_not_required'),
                    'not_started' => __('filament-admin.resources.credit_bookings.stepper.financing_not_started'),
                    default => __('filament-admin.resources.credit_bookings.stepper.stages.stage', ['number' => (int) str_replace('stage_', '', $step['key'])]),
                };

                return [
                    'label' => $label,
                    'state' => $step['state'],
                    'description' => $step['description'] ?? null,
                ];
            })
            ->values()
            ->all();

        return ProcessStepper::render($steps);
    }

    protected static function titleTransferStepper(SalesReservation $record): \Illuminate\Support\HtmlString
    {
        $steps = collect(app(CreditProcessStepBuilder::class)->titleTransferStepsForReservation($record))
            ->map(function (array $step): array {
                $label = match ($step['key']) {
                    'not_started' => __('filament-admin.resources.credit_bookings.stepper.transfer_not_started'),
                    'preparation' => __('filament-admin.resources.credit_bookings.stepper.transfer_steps.preparation'),
                    'scheduled' => __('filament-admin.resources.credit_bookings.stepper.transfer_steps.scheduled'),
                    'completed' => __('filament-admin.resources.credit_bookings.stepper.transfer_steps.completed'),
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
