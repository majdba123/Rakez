<?php

namespace App\Filament\Admin\Resources\CreditNotifications\Pages;

use App\Filament\Admin\Resources\CreditNotifications\CreditNotificationResource;
use App\Services\Credit\CreditNotificationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCreditNotifications extends ListRecords
{
    protected static string $resource = CreditNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllRead')
                ->label(__('filament-admin.resources.credit_notifications.actions.mark_all_read'))
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => CreditNotificationResource::canManageNotifications())
                ->action(function (): void {
                    app(CreditNotificationService::class)->markAllDepartmentNotificationsAsRead('credit');

                    Notification::make()
                        ->success()
                        ->title(__('filament-admin.resources.credit_notifications.notifications.all_marked_read'))
                        ->send();
                }),
        ];
    }
}
