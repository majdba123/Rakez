<?php

namespace App\Http\Requests\ProjectManagement;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePreparationStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = Contract::find($this->route('id'));
        return $contract && $this->user()?->can('update', $contract);
    }

    public function rules(): array
    {
        return [
            'document_link' => ['nullable', 'string', 'url', 'max:2048'],
            'entry_date' => ['nullable', 'date'],
            'mark_complete' => ['nullable', 'boolean'],
        ];
    }
}
