<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRewardRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_reward_id' => (int) $this->project_reward_id,
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'type' => $this->user->type,
            ] : null),
            'recipient_type' => $this->recipient_type,
            'sales_reservation_id' => $this->sales_reservation_id !== null ? (int) $this->sales_reservation_id : null,
            'sales_reservation_participant_id' => $this->sales_reservation_participant_id !== null ? (int) $this->sales_reservation_participant_id : null,
            'source_scope' => $this->source_scope,
            'source_type' => $this->source_type,
            'percentage' => $this->percentage !== null ? (float) $this->percentage : null,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'approved_by' => $this->approved_by !== null ? (int) $this->approved_by : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by !== null ? (int) $this->rejected_by : null,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'paid_by' => $this->paid_by !== null ? (int) $this->paid_by : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
