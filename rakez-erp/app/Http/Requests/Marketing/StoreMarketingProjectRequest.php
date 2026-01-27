<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'assigned_team_leader' => 'nullable|exists:users,id',
            'status' => 'nullable|string',
        ];
    }
}
