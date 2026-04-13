<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\AiOverviewStatsWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class AiOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/ai-overview';

    protected static ?string $title = 'AI Governance Overview';

    protected static ?string $navigationLabel = 'AI Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string | \UnitEnum | null $navigationGroup = 'AI & Knowledge';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePageAny('AI & Knowledge', [
            'ai.knowledge.view',
            'ai.calls.view',
        ]);
    }

    public function getWidgets(): array
    {
        return [
            AiOverviewStatsWidget::class,
        ];
    }
}
