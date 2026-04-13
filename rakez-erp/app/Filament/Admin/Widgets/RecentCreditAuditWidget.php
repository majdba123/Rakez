<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\GovernanceAuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentCreditAuditWidget extends TableWidget
{
    use HasGovernanceAuthorization;

    protected static ?string $heading = 'Recent Credit Actions';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Credit Oversight', 'credit.dashboard.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GovernanceAuditLog::query()
                    ->with('actor')
                    ->where('event', 'like', 'governance.credit.%')
                    ->latest('created_at')
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('governance.credit.', '', $state)),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->default('System'),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}
