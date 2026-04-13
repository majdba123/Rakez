<?php

namespace App\Filament\Admin\Resources\CreditNotifications\Pages;

use App\Filament\Admin\Resources\CreditNotifications\CreditNotificationResource;
use App\Models\UserNotification;
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
                ->label('Mark All Read')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => CreditNotificationResource::canAccessGovernancePage('Credit Oversight', 'credit.dashboard.view'))
                ->action(function (): void {
                    UserNotification::query()
                        ->where('status', 'pending')
                        ->whereHas('user', fn ($query) => $query->where('type', 'credit'))
                        ->update(['status' => 'read']);

                    Notification::make()
                        ->success()
                        ->title('All credit notifications marked as read.')
                        ->send();
                }),
        ];
    }
}
