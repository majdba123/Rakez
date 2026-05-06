<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class PreviewProjectCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasPermissionTo('accounting.sold-units.view')
            || $user->hasPermissionTo('accounting.sold-units.manage')
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'base_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
