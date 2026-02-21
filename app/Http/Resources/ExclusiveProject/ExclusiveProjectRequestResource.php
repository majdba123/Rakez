<?php

namespace App\Http\Resources\ExclusiveProject;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExclusiveProjectRequestResource extends JsonResource
{
    private const STATUS_LABELS_AR = [
        'pending' => 'قيد الانتظار',
        'approved' => 'Approved',
        'rejected' => 'مرفوض',
        'contract_completed' => 'مكتمل',
    ];

    public function toArray(Request $request): array
    {
        $canCompleteContract = $this->status === 'approved' && $this->contract_id === null;

        $units = $this->relationLoaded('requestUnits')
            ? $this->requestUnits->map(fn ($u) => [
                'unit_type' => $u->unit_type,
                'count' => (int) $u->count,
                'average_price' => $u->average_price !== null ? (float) $u->average_price : null,
            ])->values()->all()
            : [];

        return [
            'id' => $this->id,
            'project_name' => $this->project_name,
            'request_date' => $this->created_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
            'status' => $this->status,
            'status_label_ar' => self::STATUS_LABELS_AR[$this->status] ?? $this->status,
            'contract_id' => $this->contract_id,
            'can_complete_contract' => $canCompleteContract,
            'complete_contract_url' => $canCompleteContract ? "exclusive-projects/{$this->id}/contract" : null,
            'estimated_units' => $this->estimated_units,
            'total_value' => $this->total_value !== null ? (float) $this->total_value : null,
            'units' => $units,
        ];
    }
}
