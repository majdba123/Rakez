<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeePlanRequest extends FormRequest
{
    private const PLATFORMS = [
        'TikTok',
        'Meta',
        'Snapchat',
        'YouTube',
        'LinkedIn',
        'X',
    ];

    private const CAMPAIGNS = [
        'Direct Communication',
        'Hand Raise',
        'Impression',
        'Sales',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marketing_project_id' => 'required|exists:marketing_projects,id',
            'user_id' => 'required|exists:users,id',
            'commission_value' => 'nullable|numeric',
            'marketing_value' => 'nullable|numeric',
            'platform_distribution' => ['nullable', 'array', function ($attribute, $value, $fail) {
                $this->validateDistribution($attribute, $value, self::PLATFORMS, $fail);
            }],
            'campaign_distribution' => ['nullable', 'array', function ($attribute, $value, $fail) {
                $this->validateDistribution($attribute, $value, self::CAMPAIGNS, $fail);
            }],
            'campaign_distribution_by_platform' => ['nullable', 'array', function ($attribute, $value, $fail) {
                $this->validateCampaignDistributionByPlatform($attribute, $value, $fail);
            }],
        ];
    }

    private function validateDistribution(string $attribute, ?array $value, array $allowed, callable $fail): void
    {
        if (!$value) {
            return;
        }

        $keys = array_keys($value);
        $invalidKeys = array_diff($keys, $allowed);
        if (!empty($invalidKeys)) {
            $fail($attribute . ' has invalid keys: ' . implode(', ', $invalidKeys));
            return;
        }

        $missingKeys = array_diff($allowed, $keys);
        if (!empty($missingKeys)) {
            $fail($attribute . ' must include: ' . implode(', ', $missingKeys));
            return;
        }

        $total = 0;
        foreach ($value as $key => $percentage) {
            if (!is_numeric($percentage) || $percentage < 0 || $percentage > 100) {
                $fail($attribute . ' percentage for ' . $key . ' must be between 0 and 100.');
                return;
            }
            $total += (float) $percentage;
        }

        if (round($total, 2) !== 100.0) {
            $fail($attribute . ' percentages must total 100.');
        }
    }

    private function validateCampaignDistributionByPlatform(string $attribute, ?array $value, callable $fail): void
    {
        if (!$value) {
            return;
        }

        $platformKeys = array_keys($value);
        $invalidPlatforms = array_diff($platformKeys, self::PLATFORMS);
        if (!empty($invalidPlatforms)) {
            $fail($attribute . ' has invalid platform keys: ' . implode(', ', $invalidPlatforms));
            return;
        }

        foreach ($value as $platform => $distribution) {
            if (!is_array($distribution)) {
                $fail($attribute . ' for ' . $platform . ' must be an array.');
                return;
            }

            $this->validateDistribution($attribute . '.' . $platform, $distribution, self::CAMPAIGNS, $fail);
        }
    }
}
