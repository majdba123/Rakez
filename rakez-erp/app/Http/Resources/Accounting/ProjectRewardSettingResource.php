<?php

namespace App\Http\Resources\Accounting;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRewardSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => (int) $this->contract_id,
            'contract' => $this->whenLoaded('contract', function () {
                return [
                    'id' => $this->contract->id,
                    'project_name' => $this->contract->project_name,
                ];
            }),
            'calculation_mode' => $this->calculation_mode,
            'reward_percentage' => $this->reward_percentage !== null ? (float) $this->reward_percentage : null,
            'source' => $this->source,
            'tax_enabled' => (bool) $this->tax_enabled,
            'vat_percentage' => (float) $this->vat_percentage,
            'assigned_bring_percentage' => (float) $this->assigned_bring_percentage,
            'assigned_convince_percentage' => (float) $this->assigned_convince_percentage,
            'assigned_close_percentage' => (float) $this->assigned_close_percentage,
            'outside_bring_percentage' => (float) $this->outside_bring_percentage,
            'outside_convince_percentage' => (float) $this->outside_convince_percentage,
            'outside_close_percentage' => (float) $this->outside_close_percentage,
            'ceo_user_id' => $this->ceo_user_id !== null ? (int) $this->ceo_user_id : null,
            'ceo_percentage' => (float) $this->ceo_percentage,
            'ceo_user' => $this->whenLoaded('ceoUser', fn () => $this->miniUser($this->ceoUser)),
            'sales_manager_user_id' => $this->sales_manager_user_id !== null ? (int) $this->sales_manager_user_id : null,
            'sales_manager_percentage' => (float) $this->sales_manager_percentage,
            'sales_manager_user' => $this->whenLoaded('salesManagerUser', fn () => $this->miniUser($this->salesManagerUser)),
            'sales_leader_user_id' => $this->sales_leader_user_id !== null ? (int) $this->sales_leader_user_id : null,
            'sales_leader_percentage' => (float) $this->sales_leader_percentage,
            'sales_leader_user' => $this->whenLoaded('salesLeaderUser', fn () => $this->miniUser($this->salesLeaderUser)),
            'group_leader_user_id' => $this->group_leader_user_id !== null ? (int) $this->group_leader_user_id : null,
            'group_leader_percentage' => (float) $this->group_leader_percentage,
            'group_leader_user' => $this->whenLoaded('groupLeaderUser', fn () => $this->miniUser($this->groupLeaderUser)),
            'external_marketer_user_id' => $this->external_marketer_user_id !== null ? (int) $this->external_marketer_user_id : null,
            'external_marketer_percentage' => (float) $this->external_marketer_percentage,
            'external_marketer_user' => $this->whenLoaded('externalMarketerUser', fn () => $this->miniUser($this->externalMarketerUser)),
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->created_by !== null ? (int) $this->created_by : null,
            'created_by_user' => $this->whenLoaded('creator', fn () => $this->miniUser($this->creator)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function miniUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
