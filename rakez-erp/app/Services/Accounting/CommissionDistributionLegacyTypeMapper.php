<?php

namespace App\Services\Accounting;

/**
 * Maps Stage 3 preview (source_scope/source_type) to legacy commission_distributions.type enum.
 *
 * Mapping notes:
 * - lead_generation/persuasion/closing correspond to bring/convince/close.
 * - The DB enum has no ceo or generic management; ceo and sales_leader preview rows use project_manager as the closest managerial bucket.
 */
class CommissionDistributionLegacyTypeMapper
{
    public function map(?string $sourceScope, string $sourceType): string
    {
        if (($sourceScope ?? '') === 'management') {
            return match ($sourceType) {
                'ceo' => 'project_manager',
                'sales_manager' => 'sales_manager',
                'sales_leader' => 'project_manager',
                'group_leader' => 'team_leader',
                'external_marketer' => 'external_marketer',
                default => 'other',
            };
        }

        return match ($sourceType) {
            'bring' => 'lead_generation',
            'convince' => 'persuasion',
            'close' => 'closing',
            default => 'other',
        };
    }
}
