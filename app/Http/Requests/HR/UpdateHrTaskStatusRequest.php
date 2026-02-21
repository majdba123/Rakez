<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:in_progress,completed,could_not_complete',
            'cannot_complete_reason' => 'required_if:status,could_not_complete|nullable|string|max:1000',
        ];
    }
}
