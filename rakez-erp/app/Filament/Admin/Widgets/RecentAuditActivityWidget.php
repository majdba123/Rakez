<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\GovernanceAuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentAuditActivityWidget extends TableWidget
{
    use HasGovernanceAuthorization;

    protected static ?int $sort = 20;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Governance Activity';

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Overview', 'admin.audit.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GovernanceAuditLog::query()
                    ->with('actor')
                    ->latest('created_at')
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(false),
                TextColumn::make('event')
                    ->badge()
                    ->sortable(false),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->default('System')
                    ->sortable(false),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => blank($state) ? '-' : class_basename($state))
                    ->sortable(false),
            ]);
    }
}
