<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class PreviewUnitCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'override_unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array{override_unit_price?: float|null} */
    public function previewOptions(): array
    {
        $raw = $this->input('override_unit_price');

        if ($raw === null || $raw === '') {
            return [];
        }

        return ['override_unit_price' => (float) $raw];
    }
}
