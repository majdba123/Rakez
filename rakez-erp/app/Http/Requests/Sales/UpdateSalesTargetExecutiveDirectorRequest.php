<?php

namespace App\Http\Requests\Sales;

use App\Enums\SalesTargetExecutiveDirectorStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesTargetExecutiveDirectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u
            && $u->can('sales.dashboard.view')
            && $u->canAccessSalesExecutiveAvailableUnitsApi();
    }

    public function rules(): array
    {
        $statuses = array_map(fn (SalesTargetExecutiveDirectorStatus $c) => $c->value, SalesTargetExecutiveDirectorStatus::cases());

        return [
            'type' => 'sometimes|string|max:100',
            'value' => 'nullable|numeric|min:0',
            'status' => ['nullable', 'string', Rule::in($statuses)],
        ];
    }
}
