<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class AssignCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.teams.manage');
    }

    public function rules(): array
    {
        return [
            'team_id' => 'required|exists:teams,id',
            'campaign_id' => 'required|exists:marketing_campaigns,id',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'The team id field is required.',
            'team_id.exists' => 'The selected team does not exist.',
            'campaign_id.required' => 'The campaign id field is required.',
            'campaign_id.exists' => 'The selected campaign does not exist.',
        ];
    }
}
