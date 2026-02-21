<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DeveloperMarketingPlanExport implements FromCollection, WithHeadings
{
    public function __construct(
        private int $contractId,
        private ?string $projectName,
        private array $planData
    ) {}

    public function headings(): array
    {
        return ['Field', 'Value'];
    }

    public function collection(): Collection
    {
        return collect([
            ['Contract ID', $this->contractId],
            ['Project', $this->projectName ?? ''],
            ['Total Budget', $this->planData['total_budget'] ?? ''],
            ['Expected Impressions', $this->planData['expected_impressions'] ?? ''],
            ['Expected Clicks', $this->planData['expected_clicks'] ?? ''],
            ['Marketing Duration', $this->planData['marketing_duration'] ?? ''],
        ]);
    }
}
