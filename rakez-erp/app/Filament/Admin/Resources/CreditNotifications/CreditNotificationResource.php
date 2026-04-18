<?php

namespace App\Filament\Admin\Resources\CreditNotifications;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\CreditNotifications\Pages\ListCreditNotifications;
use App\Filament\Admin\Resources\CreditNotifications\Pages\ViewCreditNotification;
use App\Models\UserNotification;
use App\Services\Credit\CreditNotificationService;
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

class CreditNotificationResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;

    protected static ?string $model = UserNotification::class;

    protected static ?string $slug = 'credit-notifications';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Notification Review';

    protected static string | \UnitEnum | null $navigationGroup = 'Credit Oversight';

    protected static ?int $navigationSort = 40;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('user')
                ->whereHas('user', fn (Builder $userQuery): Builder => $userQuery->where('type', 'credit'))
                ->latest())
            ->columns([
                TextColumn::make('user.name')->label(__('filament-admin.resources.credit_notifications.columns.recipient'))->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('event_type')->label(__('filament-admin.resources.credit_notifications.columns.event'))->placeholder('-')->badge(),
                TextColumn::make('message')->wrap()->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('filament-admin.resources.credit_notifications.status.pending'),
                        'read' => __('filament-admin.resources.credit_notifications.status.read'),
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('markRead')
                    ->label(__('filament-admin.resources.credit_notifications.actions.mark_read'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (UserNotification $record): bool => static::canManageNotifications() && $record->status !== 'read')
                    ->action(function (UserNotification $record): void {
                        app(CreditNotificationService::class)->markDepartmentNotificationAsRead('credit', $record->id);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.credit_notifications.notifications.marked_read'))
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament-admin.resources.credit_notifications.sections.review'))
                ->schema([
                    TextEntry::make('user.name')->label(__('filament-admin.resources.credit_notifications.columns.recipient'))->placeholder('-'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('event_type')->label(__('filament-admin.resources.credit_notifications.columns.event'))->badge()->placeholder('-'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('message')->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('filament-admin.resources.credit_notifications.sections.context'))
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
            'index' => ListCreditNotifications::route('/'),
            'view' => ViewCreditNotification::route('/{record}'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-admin.resources.credit_notifications.navigation_label');
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Credit Oversight', 'credit.dashboard.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Credit Oversight', 'credit.dashboard.view');
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

    public static function canManageNotifications(): bool
    {
        return static::canGovernanceMutation('notifications.manage');
    }

    protected static function contextState(UserNotification $record): array
    {
        return collect($record->context ?? [])
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => $value === null || $value === '' ? '-' : (string) $value])
            ->all();
    }
}
