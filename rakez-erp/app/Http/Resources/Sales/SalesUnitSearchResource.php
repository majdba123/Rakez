<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesUnitSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $area = $this->area !== null && $this->area !== '' ? (float) $this->area : null;
        $contract = $this->contract;

        return [
            'id'          => $this->id,
            'unit_number' => $this->unit_number,
            'unit_type'   => $this->unit_type,
            'status'      => $this->status,
            'price'       => (float) $this->price,
            'area'        => $this->total_area_m2 ? (float) $this->total_area_m2 : $area,
            'bedrooms'    => $this->bedrooms,
            'floor'       => $this->floor,
            'description' => $this->description,
            'project'     => [
                'id'             => $contract?->id,
                'name'           => $contract?->project_name,
                'city'           => $contract?->city?->name,
                'district'       => $contract?->district?->name,
                'developer_name' => $contract?->developer_name,
            ],
        ];
    }
}
