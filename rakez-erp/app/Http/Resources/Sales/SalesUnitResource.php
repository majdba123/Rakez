<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'unit_id' => $this->id,
            'unit_number' => $this->unit_number,
            'unit_type' => $this->unit_type,
            'area_m2' => (float) $this->area,
            'floor' => $this->floor,
            'price' => (float) $this->price,
            'unit_status' => $this->status,
            'computed_availability' => $this->computed_availability ?? 'pending',
            'can_reserve' => $this->can_reserve ?? false,
            'active_reservation' => $this->when($this->active_reservation, function () {
                return [
                    'reservation_id' => $this->active_reservation->id,
                    'status' => $this->active_reservation->status,
                    'client_name' => $this->active_reservation->client_name,
                ];
            }),
        ];
    }
}
