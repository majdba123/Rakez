<?php

namespace App\Filament\Admin\Resources\SalesAttendanceSchedules;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\SalesAttendanceSchedules\Pages\ListSalesAttendanceSchedules;
use App\Models\SalesAttendanceSchedule;
use App\Models\User;
use App\Services\Sales\SalesAttendanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesAttendanceScheduleResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = SalesAttendanceSchedule::class;

    protected static string $viewPermission = 'sales.attendance.view';

    protected static ?string $slug = 'sales-attendance-schedules';

    protected static ?string $navigationLabel = 'Attendance Schedules';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string | \UnitEnum | null $navigationGroup = 'Sales Oversight';

    protected static ?int $navigationSort = 12;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contract', 'user', 'creator'])->latest('schedule_date'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('user.name')->label('Assignee')->placeholder('-'),
                TextColumn::make('schedule_date')->date()->sortable(),
                TextColumn::make('start_time')->time('H:i')->placeholder('-'),
                TextColumn::make('end_time')->time('H:i')->placeholder('-'),
                TextColumn::make('creator.name')->label('Created By')->placeholder('-'),
            ])
            ->actions([
                Action::make('deleteSchedule')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SalesAttendanceSchedule $record): bool => static::canGovernanceMutation('sales.attendance.manage'))
                    ->action(function (SalesAttendanceSchedule $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        app(SalesAttendanceService::class)->governanceDeleteSchedule($record->id, $actor);

                        Notification::make()
                            ->success()
                            ->title('Attendance schedule removed.')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesAttendanceSchedules::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Sales Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Sales Oversight', static::$viewPermission);
    }
}
