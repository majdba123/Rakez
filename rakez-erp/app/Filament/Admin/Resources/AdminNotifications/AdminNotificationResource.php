<?php

namespace App\Filament\Admin\Resources\AdminNotifications;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\AdminNotifications\Pages\ListAdminNotifications;
use App\Models\AdminNotification;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Notification\NotificationAdminService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminNotificationResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = AdminNotification::class;

    protected static string $viewPermission = 'governance.oversight.workflow.view';

    protected static ?string $slug = 'admin-notifications';

    protected static ?string $navigationLabel = 'Admin Notifications';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBell;

    protected static string | \UnitEnum | null $navigationGroup = 'Requests & Workflow';

    protected static ?int $navigationSort = 11;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user')->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('Recipient')->placeholder('-'),
                TextColumn::make('message')->limit(80)->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'read' => 'Read',
                    ]),
            ])
            ->actions([
                Action::make('markRead')
                    ->label('Mark Read')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (AdminNotification $record): bool => static::canGovernanceMutation('notifications.manage') && $record->status !== 'read')
                    ->action(function (AdminNotification $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        app(NotificationAdminService::class)->markAdminNotificationAsRead($record);

                        $record->refresh();

                        app(GovernanceAuditLogger::class)->log('governance.workflow.admin_notification.marked_read', $record, [
                            'before' => ['status' => $beforeStatus],
                            'after' => ['status' => $record->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Admin notification marked as read.')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminNotifications::route('/'),
        ];
    }

    protected static function createPermission(): ?string
    {
        return 'notifications.manage';
    }

    protected static function editPermission(): ?string
    {
        return 'notifications.manage';
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Requests & Workflow', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Requests & Workflow', static::$viewPermission);
    }
}
