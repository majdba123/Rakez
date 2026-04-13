<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\AiAuditEntry;
use App\Models\AiInteractionLog;
use App\Models\AssistantKnowledgeEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePageAny('AI & Knowledge', [
            'ai.knowledge.view',
            'ai.calls.view',
        ]);
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Knowledge Entries', (string) AssistantKnowledgeEntry::count())
                ->description('Knowledge base records'),
            Stat::make('Active Knowledge', (string) AssistantKnowledgeEntry::active()->count())
                ->description('Entries currently exposed to users'),
            Stat::make('AI Interactions', (string) AiInteractionLog::count())
                ->description('Logged AI interactions'),
            Stat::make('AI Audit Trail', (string) AiAuditEntry::count())
                ->description('Audited AI actions'),
        ];
    }
}
