<?php

namespace App\Http\Requests\Sales;

use App\Enums\ExecutiveDirectorLineStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExecutiveDirectorLineRequest extends FormRequest
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
        $statuses = array_map(fn (ExecutiveDirectorLineStatus $c) => $c->value, ExecutiveDirectorLineStatus::cases());

        return [
            'line_type' => 'required|string|max:100',
            'value' => 'nullable|numeric|min:0',
            'status' => ['nullable', 'string', Rule::in($statuses)],
        ];
    }
}
