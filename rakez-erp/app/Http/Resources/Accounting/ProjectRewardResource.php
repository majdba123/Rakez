<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_reservation_id' => (int) $this->sales_reservation_id,
            'contract_id' => (int) $this->contract_id,
            'project_reward_setting_id' => $this->project_reward_setting_id !== null ? (int) $this->project_reward_setting_id : null,
            'contract' => $this->whenLoaded('contract', fn () => $this->contract ? [
                'id' => $this->contract->id,
                'project_name' => $this->contract->project_name,
            ] : null),
            'reservation' => $this->whenLoaded('salesReservation', fn () => $this->salesReservation ? [
                'id' => $this->salesReservation->id,
                'client_name' => $this->salesReservation->client_name,
                'status' => $this->salesReservation->status,
                'proposed_price' => $this->salesReservation->proposed_price !== null ? (float) $this->salesReservation->proposed_price : null,
            ] : null),
            'calculation_mode' => $this->calculation_mode,
            'calculation_base_amount' => (float) $this->calculation_base_amount,
            'reward_percentage' => $this->reward_percentage !== null ? (float) $this->reward_percentage : null,
            'source' => $this->source,
            'base_amount' => (float) $this->base_amount,
            'tax_enabled' => (bool) $this->tax_enabled,
            'vat_percentage' => (float) $this->vat_percentage,
            'vat_amount' => (float) $this->vat_amount,
            'total_amount' => (float) $this->total_amount,
            'distribution_pool_amount' => (float) $this->distribution_pool_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by !== null ? (int) $this->created_by : null,
            'approved_by' => $this->approved_by !== null ? (int) $this->approved_by : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by !== null ? (int) $this->rejected_by : null,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'paid_by' => $this->paid_by !== null ? (int) $this->paid_by : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'recipients' => ProjectRewardRecipientResource::collection($this->whenLoaded('recipients')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
