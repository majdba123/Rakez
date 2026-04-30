<?php

namespace App\Http\Requests\Sales;

use App\Models\SalesUnitSearchAlert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesUnitSearchAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.search_alerts.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
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

    public function rules(): array
    {
        return [
            'client_name' => 'nullable|string|max:255',
            'client_mobile' => 'required|string|max:50',
            'client_email' => 'nullable|email|max:255',
            'client_sms_opt_in' => 'boolean',
            'client_sms_opted_in_at' => 'nullable|date',
            'client_sms_locale' => 'nullable|string|max:20',
            'city_id' => 'nullable|integer|exists:cities,id',
            'district_id' => 'nullable|integer|exists:districts,id',
            'project_id' => 'nullable|integer|exists:contracts,id',
            'unit_type' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:50',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0|gte:min_price',
            'min_area' => 'nullable|numeric|min:0',
            'max_area' => 'nullable|numeric|min:0|gte:min_area',
            'min_bedrooms' => 'nullable|integer|min:0|max:255',
            'max_bedrooms' => 'nullable|integer|min:0|max:255|gte:min_bedrooms',
            'query_text' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in([
                SalesUnitSearchAlert::STATUS_ACTIVE,
                SalesUnitSearchAlert::STATUS_PAUSED,
                SalesUnitSearchAlert::STATUS_MATCHED,
                SalesUnitSearchAlert::STATUS_CANCELLED,
            ])],
            'expires_at' => 'nullable|date|after:now',
            'page' => 'prohibited',
            'per_page' => 'prohibited',
            'sort_by' => 'prohibited',
            'sort_dir' => 'prohibited',
        ];
    }
}
