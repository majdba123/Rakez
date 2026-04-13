<?php

namespace App\Filament\Admin\Resources\UserNotifications\Pages;

use App\Filament\Admin\Resources\UserNotifications\UserNotificationResource;
use App\Models\User;
use App\Services\Notification\NotificationAdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUserNotifications extends ListRecords
{
    protected static string $resource = UserNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendUserNotification')
                ->label('Send To User')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => UserNotificationResource::canCreate())
                ->form([
                    Select::make('user_id')
                        ->label('Recipient')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                    Textarea::make('message')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(NotificationAdminService::class)->sendToUser((int) $data['user_id'], (string) $data['message']);

                    Notification::make()
                        ->success()
                        ->title('User notification sent.')
                        ->send();
                }),
            Action::make('sendPublicNotification')
                ->label('Send Public')
                ->icon('heroicon-o-megaphone')
                ->visible(fn (): bool => UserNotificationResource::canCreate())
                ->form([
                    Textarea::make('message')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(NotificationAdminService::class)->sendPublic((string) $data['message']);

                    Notification::make()
                        ->success()
                        ->title('Public notification sent.')
                        ->send();
                }),
            Action::make('markAllRead')
                ->label('Mark All Read')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => UserNotificationResource::canCreate())
                ->action(function (): void {
                    app(NotificationAdminService::class)->markAllUserNotificationsAsRead();

                    Notification::make()
                        ->success()
                        ->title('All user notifications marked as read.')
                        ->send();
                }),
        ];
    }
}
