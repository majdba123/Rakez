<?php

namespace App\Support\Governance;

class GovernanceStatusCatalog
{
    /**
     * @return array<int, string>
     */
    public static function salesTargetStatuses(): array
    {
        return ['new', 'in_progress', 'completed'];
    }

    /**
     * @return array<string, string>
     */
    public static function salesTargetStatusOptions(): array
    {
        return [
            'new' => __('filament-admin.status.sales_target.new'),
            'in_progress' => __('filament-admin.status.sales_target.in_progress'),
            'completed' => __('filament-admin.status.sales_target.completed'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function marketingTaskStatuses(): array
    {
        return ['pending', 'in_progress', 'completed'];
    }

    /**
     * @return array<string, string>
     */
    public static function marketingTaskStatusOptions(): array
    {
        return [
            'pending' => __('filament-admin.status.marketing_task.pending'),
            'in_progress' => __('filament-admin.status.marketing_task.in_progress'),
            'completed' => __('filament-admin.status.marketing_task.completed'),
        ];
    }
}
