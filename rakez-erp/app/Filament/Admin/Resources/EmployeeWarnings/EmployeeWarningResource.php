<?php

namespace App\Filament\Admin\Resources\EmployeeWarnings;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\EmployeeWarnings\Pages\ListEmployeeWarnings;
use App\Models\EmployeeWarning;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\HR\EmployeeWarningService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeWarningResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = EmployeeWarning::class;

    protected static string $viewPermission = 'hr.warnings.view';

    protected static ?string $slug = 'employee-warnings';

    protected static ?string $navigationLabel = 'Employee Warnings';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string | \UnitEnum | null $navigationGroup = 'HR Oversight';

    protected static ?int $navigationSort = 12;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'issuer'])->latest('warning_date'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('employee.name')->label('Employee')->searchable()->placeholder('-'),
                TextColumn::make('issuer.name')->label('Issued By')->default('System'),
                TextColumn::make('type')->badge(),
                TextColumn::make('reason')->limit(40),
                IconColumn::make('is_auto_generated')->label('Auto')->boolean(),
                TextColumn::make('warning_date')->date(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'performance' => 'Performance',
                        'attendance' => 'Attendance',
                        'behavior' => 'Behavior',
                        'other' => 'Other',
                    ]),
            ])
            ->actions([
                Action::make('deleteWarning')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (EmployeeWarning $record): bool => static::canDelete($record))
                    ->action(function (EmployeeWarning $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        app(GovernanceAuditLogger::class)->log('governance.hr.warning.deleted', $record, [
                            'before' => [
                                'user_id' => $record->user_id,
                                'type' => $record->type,
                                'warning_date' => $record->warning_date?->format('Y-m-d'),
                            ],
                        ], $actor);

                        app(EmployeeWarningService::class)->deleteWarning($record->id);

                        Notification::make()
                            ->success()
                            ->title('Employee warning deleted.')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeWarnings::route('/'),
        ];
    }

    protected static function createPermission(): ?string
    {
        return 'hr.warnings.manage';
    }

    protected static function deletePermission(): ?string
    {
        return 'hr.warnings.manage';
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('HR Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('HR Oversight', static::$viewPermission);
    }
}
