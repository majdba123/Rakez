<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmergencyContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSalesLeader() || $this->user()->type === 'admin';
    }

    public function rules(): array
    {
        return [
            'emergency_contact_number' => 'nullable|string|max:50',
            'security_guard_number' => 'nullable|string|max:50',
        ];
    }
}
