<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Shared\UserResource;

class ContractIndexResource extends JsonResource
{



    public function toArray(Request $request): array
    {
        $unitCount = 0;
        $totalPrice = 0;

        if (is_array($this->units) && count($this->units) > 0) {
            foreach ($this->units as $unit) {
                $count = (int) ($unit['count'] ?? 0);
                $price = (float) ($unit['price'] ?? 0);
                $unitCount += $count;
                $totalPrice += ($count * $price);
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            'project_name' => $this->project_name,
            'developer_name' => $this->developer_name,
            'developer_number' => $this->developer_number,
            'city_id' => $this->city_id,
            'district_id' => $this->district_id,
            'city' => $this->city?->name,
            'district' => $this->district?->name,
            'side' => $this->side,
            'contract_type' => $this->contract_type,
            'code' => $this->code,

            'unit_count' => $unitCount,
            'total_price' => (float) $totalPrice,

            'status' => $this->status,
            'developer_requiment' => $this->developer_requiment,
            'has_photography_data' => $this->photographyDepartment ? 1 : 0,
            'has_montage_data' => $this->montageDepartment ? 1 : 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'info' => new ContractInfoResource($this->whenLoaded('info')),

            // 1 if advertiser section URL exists, 0 if not (رقم المعلن)
            'advertiser_section_url' => $this->relationLoaded('secondPartyData') && ($url = $this->secondPartyData?->advertiser_section_url) !== null && trim((string) $url) !== '' ? 1 : 0,
        ];
    }
}
