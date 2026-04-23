<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesUnitResource extends JsonResource
{
    /**
     * Transform the resource for getProjectUnits and unit details.
     * الحقول المتوقعة من الواجهة: unit_number, price, area_m2, bedrooms, bathrooms, floor, private_area, total_area, facade.
     */
    public function toArray(Request $request): array
    {
        $area = $this->area !== null && $this->area !== '' ? (float) $this->area : null;
        $totalArea = $this->total_area_m2 ?? $area;

        return [
            'id' => $this->id,
            'unit_id' => $this->id,
            'unit_number' => $this->unit_number,
            'unit_type' => $this->unit_type,
            'type' => $this->unit_type,
            'price' => (float) $this->price,
            'total_price' => (float) $this->price,
            'area' => $area,
            'area_m2' => $area,
            'bedrooms' => $this->bedrooms ?? null,
            'rooms' => $this->bedrooms ?? null,
            'bathrooms' => $this->bathrooms ?? null,
            'bathrooms_count' => $this->bathrooms ?? null,
            'floor' => $this->floor ?? null,
            'private_area' => $this->private_area_m2 ?? null,
            'private_area_m2' => $this->private_area_m2 ?? null,
            'street_width' => $this->street_width !== null ? (float) $this->street_width : null,
            'balcony_area' => $this->private_area_m2 ?? null,
            'total_area' => $totalArea,
            'total_area_m2' => $this->total_area_m2 ?? null,
            'facade' => $this->facade ?? null,
            'view' => $this->facade ?? null,
            'orientation' => $this->facade ?? null,
            'description' => $this->description ?? null,
            'description_en' => $this->description_en ?? null,
            'description_ar' => $this->description_ar ?? null,
            'unit_status' => $this->status,
            'status' => $this->status,
            'computed_availability' => $this->computed_availability ?? $this->status,
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
