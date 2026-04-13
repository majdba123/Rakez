<?php

namespace App\Services\AI\Skills\Context;

use App\Services\AI\Skills\Contracts\SectionContextBuilderContract;

class SectionContextBuilderRegistry
{
    public function __construct(
        private readonly GeneralContextBuilder $general,
        private readonly ContractsContextBuilder $contracts,
        private readonly SalesContextBuilder $sales,
        private readonly MarketingContextBuilder $marketing,
        private readonly CreditContextBuilder $credit,
        private readonly AccountingContextBuilder $accounting,
        private readonly HrContextBuilder $hr,
        private readonly InventoryContextBuilder $inventory,
        private readonly WorkflowContextBuilder $workflow,
        private readonly KnowledgeContextBuilder $knowledge,
    ) {}

    public function resolve(?string $sectionKey): SectionContextBuilderContract
    {
        return match ($sectionKey) {
            'contracts',
            'second_party',
            'departments_boards',
            'departments_photography',
            'departments_montage' => $this->contracts,

            'sales',
            'sales_reservations' => $this->sales,

            'marketing_dashboard',
            'marketing_projects',
            'marketing_tasks',
            'campaign_advisor',
            'smart_distribution',
            'campaign_funnel',
            'roas_optimizer',
            'employee_recommendation' => $this->marketing,

            'credit',
            'credit_bookings' => $this->credit,
            'accounting' => $this->accounting,
            'hr' => $this->hr,
            'units',
            'units_csv' => $this->inventory,
            'notifications' => $this->workflow,
            'ai_calls' => $this->knowledge,
            'general' => $this->knowledge,
            default => $this->general,
        };
    }
}
