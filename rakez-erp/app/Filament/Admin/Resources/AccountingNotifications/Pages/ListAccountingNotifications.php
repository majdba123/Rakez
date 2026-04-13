<?php

namespace App\Filament\Admin\Resources\AccountingNotifications\Pages;

use App\Filament\Admin\Resources\AccountingNotifications\AccountingNotificationResource;
use App\Services\Accounting\AccountingNotificationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAccountingNotifications extends ListRecords
{
    protected static string $resource = AccountingNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllRead')
                ->label('Mark All Read')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => AccountingNotificationResource::canAccessGovernancePage('Accounting & Finance', 'accounting.notifications.view'))
                ->action(function (): void {
                    $actor = auth()->user();

                    abort_unless($actor !== null, 403);

                    app(AccountingNotificationService::class)->markAllAsRead($actor->id);

                    Notification::make()
                        ->success()
                        ->title('All accounting notifications marked as read.')
                        ->send();
                }),
        ];
    }
}
