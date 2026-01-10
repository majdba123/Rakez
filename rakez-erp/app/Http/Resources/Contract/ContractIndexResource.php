<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Shared\UserResource;

class ContractIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array (for list/index views)
     */
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
            'city' => $this->city,
            'district' => $this->district,

            'unit_count' => $unitCount,
            'total_price' => (float) $totalPrice,

            'status' => $this->status,
            'developer_requiment' => $this->developer_requiment,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
