<?php

namespace App\Filament\Admin\Resources\AccountingDeposits;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\AccountingDeposits\Pages\ListAccountingDeposits;
use App\Models\Deposit;
use App\Models\User;
use App\Services\Accounting\AccountingDepositService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountingDepositResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = Deposit::class;

    protected static string $viewPermission = 'accounting.deposits.view';

    protected static ?string $slug = 'accounting-deposits';

    protected static ?string $navigationLabel = 'Deposits';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string | \UnitEnum | null $navigationGroup = 'Accounting & Finance';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['contract', 'contractUnit', 'confirmedBy', 'salesReservation.marketingEmployee'])
                ->latest('payment_date'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('sales_reservation_id')->label('Booking')->sortable()->placeholder('-'),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('contractUnit.unit_number')->label('Unit')->placeholder('-'),
                TextColumn::make('client_name')->searchable()->placeholder('-'),
                TextColumn::make('amount')->money('AED')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('salesReservation.credit_status')->label('Credit Status')->badge()->placeholder('-'),
                TextColumn::make('payment_method')->badge(),
                TextColumn::make('commission_source')->label('Source')->badge(),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('salesReservation.marketingEmployee.name')->label('Sales Owner')->placeholder('-'),
                TextColumn::make('confirmedBy.name')->label('Confirmed By')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'received' => 'Received',
                        'confirmed' => 'Confirmed',
                        'refunded' => 'Refunded',
                    ]),
                SelectFilter::make('commission_source')
                    ->options([
                        'owner' => 'Owner',
                        'buyer' => 'Buyer',
                    ]),
                SelectFilter::make('confirmed_by')
                    ->label('Confirmed By')
                    ->relationship('confirmedBy', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('payment_date_range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('payment_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('payment_date', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('confirmDeposit')
                    ->label('Confirm Receipt')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Deposit $record): bool => static::canGovernanceMutation('accounting.deposits.manage') && $record->status === 'pending')
                    ->action(function (Deposit $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            abort(403);
                        }

                        $beforeStatus = $record->status;
                        $deposit = app(AccountingDepositService::class)->confirmDepositReceipt($record->id, $actor->id);

                        app(GovernanceAuditLogger::class)->log('governance.accounting.deposit.confirmed', $deposit, [
                            'before' => [
                                'status' => $beforeStatus,
                                'confirmed_by' => $record->confirmed_by,
                                'confirmed_at' => optional($record->confirmed_at)?->toDateTimeString(),
                            ],
                            'after' => [
                                'status' => $deposit->status,
                                'confirmed_by' => $deposit->confirmed_by,
                                'confirmed_at' => optional($deposit->confirmed_at)?->toDateTimeString(),
                            ],
                            'amount' => (float) $deposit->amount,
                            'reservation_id' => $deposit->sales_reservation_id,
                            'commission_source' => $deposit->commission_source,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Deposit receipt confirmed.')
                            ->send();
                    }),
                Action::make('processRefund')
                    ->label('Process Refund')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Deposit $record): bool => static::canGovernanceMutation('accounting.deposits.manage')
                        && $record->isRefundable()
                        && in_array($record->status, ['received', 'confirmed'], true))
                    ->action(function (Deposit $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            abort(403);
                        }

                        $beforeStatus = $record->status;

                        try {
                            $deposit = app(AccountingDepositService::class)->processRefund($record->id);

                            app(GovernanceAuditLogger::class)->log('governance.accounting.deposit.refunded', $deposit, [
                                'before' => [
                                    'status' => $beforeStatus,
                                    'refunded_at' => optional($record->refunded_at)?->toDateTimeString(),
                                ],
                                'after' => [
                                    'status' => $deposit->status,
                                    'refunded_at' => optional($deposit->refunded_at)?->toDateTimeString(),
                                ],
                                'amount' => (float) $deposit->amount,
                                'reservation_id' => $deposit->sales_reservation_id,
                                'commission_source' => $deposit->commission_source,
                            ], $actor);

                            Notification::make()
                                ->success()
                                ->title('Deposit refunded.')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Refund could not be processed.')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ]);
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Accounting & Finance', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Accounting & Finance', static::$viewPermission);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingDeposits::route('/'),
        ];
    }
}
