<?php

namespace App\Filament\Admin\Resources\UserNotifications;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\UserNotifications\Pages\ListUserNotifications;
use App\Models\User;
use App\Models\UserNotification;
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

class UserNotificationResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = UserNotification::class;

    protected static string $viewPermission = 'governance.oversight.workflow.view';

    protected static ?string $slug = 'user-notifications';

    protected static ?string $navigationLabel = 'User Notifications';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string | \UnitEnum | null $navigationGroup = 'Requests & Workflow';

    protected static ?int $navigationSort = 12;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user')->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('Recipient')->default('Public'),
                TextColumn::make('event_type')->label('Event')->badge()->placeholder('-'),
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
                    ->visible(fn (UserNotification $record): bool => static::canGovernanceMutation('notifications.manage') && $record->status !== 'read')
                    ->action(function (UserNotification $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        app(NotificationAdminService::class)->markUserNotificationAsRead($record);

                        $record->refresh();

                        app(GovernanceAuditLogger::class)->log('governance.workflow.user_notification.marked_read', $record, [
                            'before' => ['status' => $beforeStatus],
                            'after' => ['status' => $record->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('User notification marked as read.')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserNotifications::route('/'),
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
