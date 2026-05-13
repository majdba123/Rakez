<?php

namespace App\Services\Accounting;

use App\Models\Contract;
use App\Models\ProjectCommissionSetting;
use Illuminate\Validation\ValidationException;

class ProjectCommissionPreviewService
{
    public function __construct(
        private ProjectCommissionCalculator $calculator,
        private ContractCommissionTermsResolver $resolver,
    ) {}

    /** @param  array{base_amount?: float|null} $data */
    public function previewProject(Contract $project, array $data): array
    {
        $baseRaw = $data['base_amount'] ?? null;

        if ($baseRaw === null || (float) $baseRaw <= 0) {
            throw ValidationException::withMessages([
                'base_amount' => 'base_amount is required and must be greater than 0.',
            ]);
        }

        $baseAmount = round((float) $baseRaw, 2);

        $setting = ProjectCommissionSetting::query()
            ->where('project_id', $project->id)
            ->where('is_active', true)
            ->first();

        if (!$setting instanceof ProjectCommissionSetting) {
            throw ValidationException::withMessages([
                'project_commission_setting' => 'No active project commission setting for this project.',
            ]);
        }

        // Commission terms always come from the Contract, not the stored setting snapshot
        $terms = $this->resolver->resolve($project);

        $result = $this->calculator->calculate(
            $terms['commission_source'],
            $baseAmount,
            $terms['commission_percentage'],
        );

        return [
            'project_id'                => (int) $project->id,
            'base_amount'               => $result['base_amount'],
            'commission_source'         => $result['source'],
            'commission_percentage'     => (float) $result['commission_percentage'],
            'formula_key'               => $result['formula_key'],
            'project_commission_amount' => $result['commission_amount'],
        ];
    }
}
