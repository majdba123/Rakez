<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'nullable|string|max:32|unique:teams,code',
            'name' => 'required|string|max:255|unique:teams,name',
            'description' => 'nullable|string',
        ];
    }
}


