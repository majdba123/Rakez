<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignExecutiveDirectorLineTeamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && ($u->isAdmin() || $u->hasRole('admin') || $u->isSalesTeamManager());
    }

    public function rules(): array
    {
        return [
            'team_ids' => 'required|array|max:100',
            'team_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('teams', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
