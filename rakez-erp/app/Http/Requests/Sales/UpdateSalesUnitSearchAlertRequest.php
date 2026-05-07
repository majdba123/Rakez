<?php

namespace App\Http\Requests\Sales;

use App\Models\SalesUnitSearchAlert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesUnitSearchAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.search_alerts.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('client_sms_opt_in')) {
            $smsOptIn = $this->boolean('client_sms_opt_in');
            $this->merge([
                'client_sms_opt_in' => $smsOptIn,
                'client_sms_opted_in_at' => $smsOptIn
                    ? (! $this->input('client_sms_opted_in_at')
                        ? now()->toDateTimeString()
                        : $this->input('client_sms_opted_in_at'))
                    : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'client_name' => 'sometimes|nullable|string|max:255',
            'client_mobile' => 'sometimes|required|string|max:50',
            'client_email' => 'sometimes|nullable|email|max:255',
            'client_sms_opt_in' => 'sometimes|boolean',
            'client_sms_opted_in_at' => 'sometimes|nullable|date',
            'client_sms_locale' => 'sometimes|nullable|string|max:20',
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
            'district_id' => 'sometimes|nullable|integer|exists:districts,id',
            'project_id' => 'sometimes|nullable|integer|exists:contracts,id',
            'unit_type' => 'sometimes|nullable|string|max:255',
            'floor' => 'sometimes|nullable|string|max:50',
            'min_price' => 'sometimes|nullable|numeric|min:0',
            'max_price' => 'sometimes|nullable|numeric|min:0',
            'min_area' => 'sometimes|nullable|numeric|min:0',
            'max_area' => 'sometimes|nullable|numeric|min:0',
            'min_bedrooms' => 'sometimes|nullable|integer|min:0|max:255',
            'max_bedrooms' => 'sometimes|nullable|integer|min:0|max:255',
            'query_text' => 'sometimes|nullable|string|max:255',
            'status' => ['sometimes', Rule::in([
                SalesUnitSearchAlert::STATUS_ACTIVE,
                SalesUnitSearchAlert::STATUS_PAUSED,
                SalesUnitSearchAlert::STATUS_MATCHED,
                SalesUnitSearchAlert::STATUS_CANCELLED,
            ])],
            'expires_at' => 'sometimes|nullable|date|after:now',
            'page' => 'prohibited',
            'per_page' => 'prohibited',
            'sort_by' => 'prohibited',
            'sort_dir' => 'prohibited',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $alert = $this->route('alert');

            foreach ([
                ['min_price', 'max_price'],
                ['min_area', 'max_area'],
                ['min_bedrooms', 'max_bedrooms'],
            ] as [$minKey, $maxKey]) {
                $min = $this->has($minKey) ? $this->input($minKey) : $alert?->{$minKey};
                $max = $this->has($maxKey) ? $this->input($maxKey) : $alert?->{$maxKey};

                if ($min !== null && $max !== null && (float) $min > (float) $max) {
                    $validator->errors()->add($maxKey, "The {$maxKey} field must be greater than or equal to {$minKey}.");
                }
            }
        });
    }
}
