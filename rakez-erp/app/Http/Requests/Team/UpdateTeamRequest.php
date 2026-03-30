<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teamId = $this->route('id');

        return [
            'code' => 'nullable|string|max:32|unique:teams,code,' . $teamId,
            'name' => 'sometimes|required|string|max:255|unique:teams,name,' . $teamId,
            'description' => 'nullable|string',
        ];
    }
}


