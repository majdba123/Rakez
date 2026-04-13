<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\UserNotification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PendingRequestNotificationsWidget extends TableWidget
{
    use HasGovernanceAuthorization;

    protected static ?string $heading = 'Pending User Notifications';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Requests & Workflow', 'governance.approvals.center.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                UserNotification::query()
                    ->with('user')
                    ->pending()
                    ->latest('created_at')
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('user.name')->label('Recipient')->default('Public'),
                TextColumn::make('event_type')->label('Event')->badge()->placeholder('-'),
                TextColumn::make('message')->label('Message')->limit(80),
                TextColumn::make('created_at')->label('Created')->dateTime(),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}
