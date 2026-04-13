<?php

namespace App\Filament\Admin\Resources\CommissionDistributions;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\CommissionDistributions\Pages\ListCommissionDistributions;
use App\Models\CommissionDistribution;
use App\Models\User;
use App\Services\Accounting\AccountingCommissionService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommissionDistributionResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = CommissionDistribution::class;

    protected static string $viewPermission = 'commissions.view';

    protected static ?string $slug = 'commission-distributions';

    protected static ?string $navigationLabel = 'Commission Distributions';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string | \UnitEnum | null $navigationGroup = 'Accounting & Finance';

    protected static ?int $navigationSort = 11;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['commission', 'user', 'approver'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('commission_id')->label('Commission')->sortable(),
                TextColumn::make('recipient')
                    ->label('Recipient')
                    ->state(fn (CommissionDistribution $record): string => $record->getDisplayName())
                    ->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('percentage')->suffix('%'),
                TextColumn::make('amount')->money('AED')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('approver.name')->label('Approved By')->placeholder('-'),
                TextColumn::make('approved_at')->dateTime()->placeholder('-'),
                TextColumn::make('paid_at')->dateTime()->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'employee' => 'Employee',
                        'external_marketer' => 'External Marketer',
                        'other' => 'Other',
                    ]),
                Filter::make('approved_date_range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('approved_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('approved_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('approveDistribution')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CommissionDistribution $record): bool => static::canGovernanceMutation('accounting.commissions.approve') && $record->status === 'pending')
                    ->action(function (CommissionDistribution $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            abort(403);
                        }

                        $beforeStatus = $record->status;
                        $distribution = app(AccountingCommissionService::class)->approveDistribution($record->id, $actor->id);

                        app(GovernanceAuditLogger::class)->log('governance.accounting.commission.approved', $distribution, [
                            'before' => [
                                'status' => $beforeStatus,
                                'approved_by' => $record->approved_by,
                                'approved_at' => optional($record->approved_at)?->toDateTimeString(),
                            ],
                            'after' => [
                                'status' => $distribution->status,
                                'approved_by' => $distribution->approved_by,
                                'approved_at' => optional($distribution->approved_at)?->toDateTimeString(),
                            ],
                            'commission_id' => $distribution->commission_id,
                            'recipient_user_id' => $distribution->user_id,
                            'amount' => (float) $distribution->amount,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Commission distribution approved.')
                            ->send();
                    }),
                Action::make('rejectDistribution')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Rejection Notes')
                            ->rows(4)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (CommissionDistribution $record): bool => static::canGovernanceMutation('accounting.commissions.approve') && $record->status === 'pending')
                    ->action(function (CommissionDistribution $record, array $data): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            abort(403);
                        }

                        $beforeStatus = $record->status;
                        $distribution = app(AccountingCommissionService::class)->rejectDistribution(
                            $record->id,
                            $actor->id,
                            $data['notes'] ?? null,
                        );

                        app(GovernanceAuditLogger::class)->log('governance.accounting.commission.rejected', $distribution, [
                            'before' => [
                                'status' => $beforeStatus,
                                'approved_by' => $record->approved_by,
                            ],
                            'after' => [
                                'status' => $distribution->status,
                                'approved_by' => $distribution->approved_by,
                                'approved_at' => optional($distribution->approved_at)?->toDateTimeString(),
                                'notes' => $distribution->notes,
                            ],
                            'commission_id' => $distribution->commission_id,
                            'recipient_user_id' => $distribution->user_id,
                            'amount' => (float) $distribution->amount,
                            'rejection_notes' => $data['notes'] ?? null,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Commission distribution rejected.')
                            ->send();
                    }),
                Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (CommissionDistribution $record): bool => static::canGovernanceMutation('commissions.mark_paid') && $record->status === 'approved')
                    ->action(function (CommissionDistribution $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            abort(403);
                        }

                        $beforeStatus = $record->status;
                        $distribution = app(AccountingCommissionService::class)->confirmCommissionPayment($record->id);

                        app(GovernanceAuditLogger::class)->log('governance.accounting.commission.marked_paid', $distribution, [
                            'before' => [
                                'status' => $beforeStatus,
                                'paid_at' => optional($record->paid_at)?->toDateTimeString(),
                            ],
                            'after' => [
                                'status' => $distribution->status,
                                'paid_at' => optional($distribution->paid_at)?->toDateTimeString(),
                            ],
                            'commission_id' => $distribution->commission_id,
                            'recipient_user_id' => $distribution->user_id,
                            'amount' => (float) $distribution->amount,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Commission distribution marked as paid.')
                            ->send();
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
            'index' => ListCommissionDistributions::route('/'),
        ];
    }
}
