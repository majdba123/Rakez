<?php

namespace App\Filament\Admin\Resources\AdminNotifications\Pages;

use App\Filament\Admin\Resources\AdminNotifications\AdminNotificationResource;
use App\Models\User;
use App\Services\Notification\NotificationAdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAdminNotifications extends ListRecords
{
    protected static string $resource = AdminNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendAdminNotification')
                ->label('Send Admin Notification')
                ->icon('heroicon-o-bell')
                ->visible(fn (): bool => AdminNotificationResource::canCreate())
                ->form([
                    Select::make('user_id')
                        ->label('Admin User')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => User::query()->role([
                            'admin',
                            'erp_admin',
                            'super_admin',
                            'workflow_admin',
                            'accounting_admin',
                            'credit_admin',
                            'projects_admin',
                            'sales_admin',
                            'hr_admin',
                            'marketing_admin',
                            'inventory_admin',
                            'ai_admin',
                        ])->orderBy('name')->pluck('name', 'id')->all()),
                    Textarea::make('message')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(NotificationAdminService::class)->sendAdmin((int) $data['user_id'], (string) $data['message']);

                    Notification::make()
                        ->success()
                        ->title('Admin notification sent.')
                        ->send();
                }),
            Action::make('markAllRead')
                ->label('Mark All Read')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => AdminNotificationResource::canCreate())
                ->action(function (): void {
                    app(NotificationAdminService::class)->markAllAdminNotificationsAsRead();

                    Notification::make()
                        ->success()
                        ->title('All admin notifications marked as read.')
                        ->send();
                }),
        ];
    }
}
