<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class AssignTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.projects.view');
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ];
    }
}
