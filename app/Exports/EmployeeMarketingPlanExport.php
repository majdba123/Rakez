<?php

namespace App\Exports;

use App\Models\EmployeeMarketingPlan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeMarketingPlanExport implements FromCollection, WithHeadings
{
    public function __construct(
        private EmployeeMarketingPlan $plan
    ) {}

    public function headings(): array
    {
        return ['Field', 'Value'];
    }

    public function collection(): Collection
    {
        $plan = $this->plan->load(['user', 'marketingProject.contract']);
        $rows = [
            ['Plan ID', $plan->id],
            ['Project', $plan->marketingProject->contract->project_name ?? ''],
            ['User', $plan->user->name ?? ''],
            ['Commission Value', $plan->commission_value],
            ['Marketing Value', $plan->marketing_value],
        ];

        $rows[] = [''];
        $rows[] = ['Platform Distribution', ''];
        foreach ($plan->platform_distribution ?? [] as $platform => $percentage) {
            $rows[] = [$platform, $percentage];
        }

        $rows[] = [''];
        $rows[] = ['Campaign Distribution', ''];
        foreach ($plan->campaign_distribution ?? [] as $campaign => $percentage) {
            $rows[] = [$campaign, $percentage];
        }

        $rows[] = [''];
        $rows[] = ['Campaign Distribution By Platform', ''];
        foreach ($plan->campaign_distribution_by_platform ?? [] as $platform => $distribution) {
            $rows[] = [$platform, ''];
            foreach ((array) $distribution as $campaign => $percentage) {
                $rows[] = ['  ' . $campaign, $percentage];
            }
        }

        return collect($rows);
    }
}
