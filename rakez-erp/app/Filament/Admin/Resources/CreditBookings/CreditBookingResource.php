<?php

namespace App\Filament\Admin\Resources\CreditBookings;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\CreditBookings\Pages\ListCreditBookings;
use App\Filament\Admin\Resources\CreditBookings\Pages\ViewCreditBooking;
use App\Models\SalesReservation;
use App\Models\User;
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
                    ->label('Project')
                    ->state(fn (SalesReservation $record): string => static::projectName($record))
                    ->searchable(),
                TextColumn::make('contractUnit.unit_number')
                    ->label('Unit')
                    ->placeholder('-'),
                TextColumn::make('client_name')
                    ->label('Client')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('credit_status')->label('Credit Status')->badge(),
                TextColumn::make('purchase_mechanism')
                    ->label('Purchase')
                    ->state(fn (SalesReservation $record): string => static::purchaseMechanismLabel($record->purchase_mechanism)),
                IconColumn::make('down_payment_confirmed')
                    ->label('Deposit Confirmed')
                    ->boolean(),
                TextColumn::make('financingTracker.overall_status')
                    ->label('Financing')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('titleTransfer.status')
                    ->label('Title Transfer')
                    ->badge()
                    ->placeholder('-'),
                IconColumn::make('has_claim_file')
                    ->label('Claim File')
                    ->boolean()
                    ->state(fn (SalesReservation $record): bool => $record->claimFile !== null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'under_negotiation' => 'Under Negotiation',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('credit_status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'title_transfer' => 'Title Transfer',
                        'sold' => 'Sold',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('purchase_mechanism')
                    ->options([
                        'cash' => 'Cash',
                        'supported_bank' => 'Supported Bank',
                        'unsupported_bank' => 'Unsupported Bank',
                    ]),
                SelectFilter::make('financing_status')
                    ->label('Financing Status')
                    ->options([
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->whereHas('financingTracker', fn (Builder $q) => $q->where('overall_status', $data['value']))
                        : $query),
                TernaryFilter::make('has_title_transfer')
                    ->label('Has Title Transfer')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('titleTransfer'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('titleTransfer'),
                    ),
                TernaryFilter::make('has_claim_file')
                    ->label('Has Claim File')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('claimFile'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('claimFile'),
                    ),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('editClient')
                    ->label('Edit Client')
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
                            ->title('Booking client details updated.')
                            ->send();
                    }),
                Action::make('logClientContact')
                    ->label('Log Contact')
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
                            ->title('Credit client contact logged.')
                            ->send();
                    }),
                Action::make('cancelBooking')
                    ->label('Cancel')
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
                            ->title('Booking cancelled.')
                            ->send();
                    }),
                Action::make('advanceFinancing')
                    ->label('Advance Financing')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('credit.financing.manage') && $record->isBankFinancing() && ! in_array($record->credit_status, ['sold', 'rejected'], true))
                    ->form([
                        TextInput::make('bank_name')->maxLength(100),
                        TextInput::make('client_salary')->numeric()->minValue(0),
                        Select::make('employment_type')->options([
                            'government' => 'Government',
                            'private' => 'Private',
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
                            ->title('Financing workflow advanced.')
                            ->send();
                    }),
                Action::make('rejectFinancing')
                    ->label('Reject Financing')
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
                            ->title('Financing request rejected.')
                            ->send();
                    }),
                Action::make('generateClaimFile')
                    ->label('Generate Claim File')
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
                            ->title('Claim file generated.')
                            ->send();
                    }),
                Action::make('generateClaimPdf')
                    ->label('Generate Claim PDF')
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
                            ->title('Claim file PDF ready.')
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reservation')
                ->schema([
                    TextEntry::make('id')->label('Booking ID'),
                    TextEntry::make('project_name')
                        ->label('Project')
                        ->state(fn (SalesReservation $record): string => static::projectName($record)),
                    TextEntry::make('contractUnit.unit_number')->label('Unit')->placeholder('-'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('credit_status')->label('Credit Status')->badge(),
                    TextEntry::make('purchase_mechanism')
                        ->label('Purchase')
                        ->state(fn (SalesReservation $record): string => static::purchaseMechanismLabel($record->purchase_mechanism)),
                    IconEntry::make('down_payment_confirmed')->label('Deposit Confirmed')->boolean(),
                    TextEntry::make('confirmed_at')->label('Confirmed At')->dateTime()->placeholder('-'),
                ])
                ->columns(2),
            Section::make('Client and Financials')
                ->schema([
                    TextEntry::make('client_name')->label('Client')->placeholder('-'),
                    TextEntry::make('client_mobile')->label('Mobile')->placeholder('-'),
                    TextEntry::make('client_nationality')->label('Nationality')->placeholder('-'),
                    TextEntry::make('client_iban')->label('IBAN')->placeholder('-'),
                    TextEntry::make('down_payment_amount')->label('Down Payment')->money('AED')->placeholder('-'),
                    TextEntry::make('payment_installments_count')
                        ->label('Installments')
                        ->state(fn (SalesReservation $record): string => (string) $record->paymentInstallments()->count()),
                    TextEntry::make('remaining_payment_plan')
                        ->label('Remaining Payment Plan')
                        ->state(fn (SalesReservation $record): string => number_format($record->getPaymentPlanRemaining(), 2) . ' AED'),
                    IconEntry::make('requires_accounting_confirmation')
                        ->label('Needs Accounting Confirmation')
                        ->state(fn (SalesReservation $record): bool => $record->requiresAccountingConfirmation())
                        ->boolean(),
                ])
                ->columns(2),
            Section::make('Financing')
                ->schema([
                    TextEntry::make('financingTracker.overall_status')->label('Overall Status')->badge()->default('No financing tracker'),
                    TextEntry::make('financingTracker.assignedUser.name')->label('Assigned To')->placeholder('-'),
                    TextEntry::make('financingTracker.current_stage')
                        ->label('Current Stage')
                        ->state(fn (SalesReservation $record): string => $record->financingTracker ? (string) $record->financingTracker->getCurrentStage() : '-'),
                    TextEntry::make('financingTracker.remaining_days')
                        ->label('Remaining Days')
                        ->state(fn (SalesReservation $record): string => $record->financingTracker?->getRemainingDays() !== null ? (string) $record->financingTracker->getRemainingDays() : '-'),
                    KeyValueEntry::make('financing_progress')
                        ->label('Progress Summary')
                        ->state(fn (SalesReservation $record): array => static::financingProgressState($record))
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Transfer and Claim Review')
                ->schema([
                    TextEntry::make('titleTransfer.status')->label('Title Transfer Status')->badge()->placeholder('-'),
                    TextEntry::make('titleTransfer.scheduled_date')->label('Scheduled Date')->date()->placeholder('-'),
                    TextEntry::make('titleTransfer.completed_date')->label('Completed Date')->date()->placeholder('-'),
                    TextEntry::make('claimFile.id')
                        ->label('Claim File')
                        ->state(fn (SalesReservation $record): string => $record->claimFile?->id ? '#' . $record->claimFile->id : 'Not generated'),
                    IconEntry::make('claim_file_pdf')
                        ->label('Claim PDF')
                        ->state(fn (SalesReservation $record): bool => (bool) $record->claimFile?->hasPdf())
                        ->boolean(),
                    TextEntry::make('claimFile.total_claim_amount')->label('Claim Amount')->money('AED')->placeholder('-'),
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
            'cash' => 'Cash',
            'supported_bank' => 'Supported Bank',
            'unsupported_bank' => 'Unsupported Bank',
            default => $value ?? '-',
        };
    }

    protected static function financingProgressState(SalesReservation $record): array
    {
        $tracker = $record->financingTracker;

        if (! $tracker) {
            return ['Tracker' => 'No financing tracker'];
        }

        $state = [];

        for ($i = 1; $i <= 5; $i++) {
            $status = $tracker->getStageStatus($i);
            $deadline = $tracker->getStageDeadline($i)?->format('Y-m-d H:i');

            $state["Stage {$i}"] = $deadline ? "{$status} (deadline {$deadline})" : $status;
        }

        return $state;
    }
}
