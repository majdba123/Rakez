<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetDistributionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'marketing_project_id' => 'required|exists:marketing_projects,id',
            'plan_type' => ['required', Rule::in(['employee', 'developer'])],
            'employee_marketing_plan_id' => 'nullable|required_if:plan_type,employee|exists:employee_marketing_plans,id',
            'developer_marketing_plan_id' => 'nullable|required_if:plan_type,developer|exists:developer_marketing_plans,id',
            'total_budget' => 'required|numeric|min:0',
            'platform_distribution' => ['required', 'array', function ($attribute, $value, $fail) {
                $this->validatePlatformDistribution($attribute, $value, $fail);
            }],
            'platform_distribution.*' => 'numeric|min:0|max:100',
            'platform_objectives' => ['required', 'array', function ($attribute, $value, $fail) {
                $this->validatePlatformObjectives($attribute, $value, $fail);
            }],
            'platform_objectives.*.impression_percent' => 'numeric|min:0|max:100',
            'platform_objectives.*.lead_percent' => 'numeric|min:0|max:100',
            'platform_objectives.*.direct_contact_percent' => 'numeric|min:0|max:100',
            'platform_costs' => ['required', 'array', function ($attribute, $value, $fail) {
                $this->validatePlatformCosts($attribute, $value, $fail);
            }],
            'platform_costs.*.cpl' => 'numeric|min:0',
            'platform_costs.*.direct_contact_cost' => 'numeric|min:0',
            'cost_source' => 'nullable|array',
            'cost_source.*' => ['nullable', Rule::in(['manual', 'auto'])],
            'conversion_rate' => 'required|numeric|min:0|max:100',
            'average_booking_value' => 'required|numeric|min:0',
        ];
    }

    /**
     * Validate platform distribution percentages sum to 100%
     */
    private function validatePlatformDistribution(string $attribute, ?array $value, callable $fail): void
    {
        if (!$value || empty($value)) {
            $fail($attribute . ' is required and cannot be empty.');
            return;
        }

        $total = 0;
        foreach ($value as $platform => $percentage) {
            if (!is_numeric($percentage)) {
                $fail($attribute . ' percentage for ' . $platform . ' must be numeric.');
                return;
            }
            $total += (float) $percentage;
        }

        if (abs($total - 100) > 0.01) {
            $fail($attribute . ' percentages must total 100%. Current sum: ' . round($total, 2));
        }
    }

    /**
     * Validate platform objectives sum to 100% for each platform
     */
    private function validatePlatformObjectives(string $attribute, ?array $value, callable $fail): void
    {
        if (!$value || empty($value)) {
            $fail($attribute . ' is required and cannot be empty.');
            return;
        }

        foreach ($value as $platform => $objectives) {
            if (!is_array($objectives)) {
                $fail($attribute . ' objectives for ' . $platform . ' must be an array.');
                continue;
            }

            $impression = (float) ($objectives['impression_percent'] ?? 0);
            $lead = (float) ($objectives['lead_percent'] ?? 0);
            $directContact = (float) ($objectives['direct_contact_percent'] ?? 0);

            $total = $impression + $lead + $directContact;

            if (abs($total - 100) > 0.01) {
                $fail($attribute . ' objectives for ' . $platform . ' must total 100%. Current sum: ' . round($total, 2));
            }
        }
    }

    /**
     * Validate platform costs structure
     */
    private function validatePlatformCosts(string $attribute, ?array $value, callable $fail): void
    {
        if (!$value || empty($value)) {
            $fail($attribute . ' is required and cannot be empty.');
            return;
        }

        foreach ($value as $platform => $costs) {
            if (!is_array($costs)) {
                $fail($attribute . ' costs for ' . $platform . ' must be an array.');
                continue;
            }

            if (!isset($costs['cpl']) || !is_numeric($costs['cpl']) || $costs['cpl'] < 0) {
                $fail($attribute . ' CPL for ' . $platform . ' must be a non-negative number.');
            }

            if (!isset($costs['direct_contact_cost']) || !is_numeric($costs['direct_contact_cost']) || $costs['direct_contact_cost'] < 0) {
                $fail($attribute . ' direct contact cost for ' . $platform . ' must be a non-negative number.');
            }
        }
    }
}
