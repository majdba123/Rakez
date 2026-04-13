<?php

namespace App\Filament\Admin\Resources\SalaryDistributions;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\SalaryDistributions\Pages\ListSalaryDistributions;
use App\Models\AccountingSalaryDistribution;
use App\Models\User;
use App\Services\Accounting\AccountingSalaryService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalaryDistributionResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = AccountingSalaryDistribution::class;

    protected static string $viewPermission = 'accounting.salaries.view';

    protected static ?string $slug = 'salary-distributions';

    protected static ?string $navigationLabel = 'Salary Distributions';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string | \UnitEnum | null $navigationGroup = 'Accounting & Finance';

    protected static ?int $navigationSort = 12;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user')->latest('year')->latest('month'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('Employee')->searchable()->placeholder('-'),
                TextColumn::make('period')->label('Period')->state(fn (AccountingSalaryDistribution $record): string => $record->getPeriodDisplay()),
                TextColumn::make('base_salary')->money('AED'),
                TextColumn::make('total_commissions')->money('AED'),
                TextColumn::make('total_amount')->money('AED')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('paid_at')->dateTime()->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->label('Created')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                    ]),
                SelectFilter::make('month')
                    ->options([
                        1 => 'January',
                        2 => 'February',
                        3 => 'March',
                        4 => 'April',
                        5 => 'May',
                        6 => 'June',
                        7 => 'July',
                        8 => 'August',
                        9 => 'September',
                        10 => 'October',
                        11 => 'November',
                        12 => 'December',
                    ]),
                SelectFilter::make('year')
                    ->options(fn (): array => AccountingSalaryDistribution::query()
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->mapWithKeys(fn (int|string $year): array => [(string) $year => (string) $year])
                        ->all()),
            ])
            ->actions([
                Action::make('approveSalary')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AccountingSalaryDistribution $record): bool => static::canGovernanceMutation('accounting.salaries.distribute') && $record->status === 'pending')
                    ->action(function (AccountingSalaryDistribution $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            abort(403);
                        }

                        $beforeStatus = $record->status;
                        $distribution = app(AccountingSalaryService::class)->approveSalaryDistribution($record->id);

                        app(GovernanceAuditLogger::class)->log('governance.accounting.salary.approved', $distribution, [
                            'before' => [
                                'status' => $beforeStatus,
                                'paid_at' => optional($record->paid_at)?->toDateTimeString(),
                            ],
                            'after' => [
                                'status' => $distribution->status,
                                'paid_at' => optional($distribution->paid_at)?->toDateTimeString(),
                            ],
                            'employee_user_id' => $distribution->user_id,
                            'period' => $distribution->getPeriodDisplay(),
                            'total_amount' => (float) $distribution->total_amount,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Salary distribution approved.')
                            ->send();
                    }),
                Action::make('markSalaryPaid')
                    ->label('Mark Paid')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (AccountingSalaryDistribution $record): bool => static::canGovernanceMutation('accounting.salaries.distribute') && $record->status === 'approved')
                    ->action(function (AccountingSalaryDistribution $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            abort(403);
                        }

                        $beforeStatus = $record->status;
                        $distribution = app(AccountingSalaryService::class)->markSalaryDistributionAsPaid($record->id);

                        app(GovernanceAuditLogger::class)->log('governance.accounting.salary.marked_paid', $distribution, [
                            'before' => [
                                'status' => $beforeStatus,
                                'paid_at' => optional($record->paid_at)?->toDateTimeString(),
                            ],
                            'after' => [
                                'status' => $distribution->status,
                                'paid_at' => optional($distribution->paid_at)?->toDateTimeString(),
                            ],
                            'employee_user_id' => $distribution->user_id,
                            'period' => $distribution->getPeriodDisplay(),
                            'total_amount' => (float) $distribution->total_amount,
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Salary distribution marked as paid.')
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
            'index' => ListSalaryDistributions::route('/'),
        ];
    }
}
