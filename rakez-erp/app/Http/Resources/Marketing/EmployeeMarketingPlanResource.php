<?php

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeMarketingPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'marketing_project_id' => $this->marketing_project_id,
            'user' => $this->user->name ?? null,
            'commission_value' => $this->commission_value,
            'marketing_value' => $this->marketing_value,
            'platform_distribution' => $this->platform_distribution,
            'campaign_distribution' => $this->campaign_distribution,
            'campaign_distribution_by_platform' => $this->campaign_distribution_by_platform,
            'campaigns' => $this->whenLoaded('campaigns'),
        ];
    }
}
