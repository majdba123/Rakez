<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['contract.city', 'contract.district', 'contractUnit', 'contractUnits', 'leader', 'marketer']);

        $contract = $this->contract;
        $legacyUnits = $this->relationLoaded('contractUnits') ? $this->contractUnits : collect();
        $unitsList = $legacyUnits->map(fn ($u) => [
            'id' => $u->id,
            'unit_number' => $u->unit_number ?? (string) $u->id,
        ])->values()->all();
        if ($unitsList === [] && $this->contractUnit) {
            $unitsList = [[
                'id' => $this->contractUnit->id,
                'unit_number' => $this->contractUnit->unit_number ?? (string) $this->contractUnit->id,
            ]];
        }

        $mustSell = (int) ($this->must_sell_units_count ?? 1);
        $targetValue = $this->assigned_target_value !== null
            ? (float) $this->assigned_target_value
            : null;

        return [
            'item_type' => 'target',
            'target_id' => $this->id,
            'contract_id' => $this->contract_id,
            'project_name' => $contract?->project_name ?? 'N/A',
            'project_location' => [
                'city_id' => $contract?->city_id,
                'city_name' => $contract?->relationLoaded('city') && $contract->city ? $contract->city->name : null,
                'district_id' => $contract?->district_id,
                'district_name' => $contract?->relationLoaded('district') && $contract->district ? $contract->district->name : null,
            ],
            'must_sell_units_count' => $mustSell,
            'assigned_target_value' => $targetValue,
            /** @deprecated Legacy inventory pivot only; new targets do not assign units. */
            'unit_number' => $this->contractUnit->unit_number ?? null,
            'contract_unit_ids' => $unitsList ? array_column($unitsList, 'id') : ($this->contract_unit_id ? [$this->contract_unit_id] : []),
            'units' => $unitsList,
            'target_type' => $this->target_type,
            'target_type_label_ar' => match ($this->target_type) {
                'reservation' => 'حجز',
                'negotiation' => 'تفاوض',
                'closing' => 'إقفال',
                default => $this->target_type,
            },
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'status' => $this->status,
            'status_label_ar' => match ($this->status) {
                'new' => 'جديد',
                'in_progress' => 'قيد التنفيذ',
                'completed' => 'منجز',
                default => $this->status,
            },
            'leader_notes' => $this->leader_notes,
            'leader_id' => $this->leader_id,
            'assigned_by' => $this->leader->name ?? 'N/A',
            'marketer_id' => $this->marketer_id,
            'marketer_name' => $this->relationLoaded('marketer') && $this->marketer ? $this->marketer->name : null,
        ];
    }
}
