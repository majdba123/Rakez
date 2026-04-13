<?php

namespace App\Filament\Admin\Resources\AccountingNotifications;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\AccountingNotifications\Pages\ListAccountingNotifications;
use App\Filament\Admin\Resources\AccountingNotifications\Pages\ViewAccountingNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Accounting\AccountingNotificationService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
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

class AccountingNotificationResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;

    protected static ?string $model = UserNotification::class;

    protected static ?string $slug = 'accounting-notifications';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Accounting Notifications';

    protected static string | \UnitEnum | null $navigationGroup = 'Accounting & Finance';

    protected static ?int $navigationSort = 13;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('user')
                ->whereHas('user', fn (Builder $userQuery): Builder => $userQuery->where('type', 'accounting'))
                ->latest())
            ->columns([
                TextColumn::make('user.name')->label('Recipient')->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('event_type')->label('Event')->placeholder('-')->badge(),
                TextColumn::make('message')->wrap()->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'read' => 'Read',
                    ]),
                SelectFilter::make('event_type')
                    ->label('Event')
                    ->options(fn (): array => UserNotification::query()
                        ->whereHas('user', fn (Builder $query): Builder => $query->where('type', 'accounting'))
                        ->whereNotNull('event_type')
                        ->distinct()
                        ->pluck('event_type', 'event_type')
                        ->sort()
                        ->all()),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('markRead')
                    ->label('Mark Read')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (UserNotification $record): bool => static::canGovernanceMutation('notifications.manage') && $record->status !== 'read')
                    ->action(function (UserNotification $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        app(AccountingNotificationService::class)->markAsRead($record->id);

                        $record->refresh();

                        app(GovernanceAuditLogger::class)->log('governance.accounting.notification.marked_read', $record, [
                            'before' => ['status' => $beforeStatus],
                            'after' => ['status' => $record->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Accounting notification marked as read.')
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Notification')
                ->schema([
                    TextEntry::make('user.name')->label('Recipient')->placeholder('-'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('event_type')->label('Event')->badge()->placeholder('-'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('message')->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Context')
                ->schema([
                    KeyValueEntry::make('context')
                        ->state(fn (UserNotification $record): array => static::contextState($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingNotifications::route('/'),
            'view' => ViewAccountingNotification::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Accounting & Finance', 'accounting.notifications.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Accounting & Finance', 'accounting.notifications.view');
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

    protected static function contextState(UserNotification $record): array
    {
        return collect($record->context ?? [])
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => $value === null || $value === '' ? '-' : (string) $value])
            ->all();
    }
}
