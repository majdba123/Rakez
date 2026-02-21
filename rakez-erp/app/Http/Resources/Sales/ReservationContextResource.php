<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'project' => [
                'project_name' => $this->contract->project_name ?? 'N/A',
                'city' => $this->contract->city ?? 'N/A',
                'district' => $this->contract->district ?? 'N/A',
            ],
            'unit' => [
                'unit_id' => $this->id,
                'unit_number' => $this->unit_number,
                'unit_type' => $this->unit_type,
                'area_m2' => (float) $this->area,
                'floor' => $this->floor,
                'price' => (float) $this->price,
            ],
            'marketing_employee' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'team' => $request->user()->team,
            ],
            'lookups' => [
                'reservation_types' => [
                    ['value' => 'confirmed_reservation', 'label' => 'Confirmed Reservation'],
                    ['value' => 'negotiation', 'label' => 'Reservation for Negotiation'],
                ],
                'payment_methods' => [
                    ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
                    ['value' => 'cash', 'label' => 'Cash'],
                    ['value' => 'bank_financing', 'label' => 'Bank Financing'],
                ],
                'down_payment_statuses' => [
                    ['value' => 'refundable', 'label' => 'Refundable'],
                    ['value' => 'non_refundable', 'label' => 'Non-refundable'],
                ],
                'purchase_mechanisms' => [
                    ['value' => 'cash', 'label' => 'Cash'],
                    ['value' => 'supported_bank', 'label' => 'Supported Bank'],
                    ['value' => 'unsupported_bank', 'label' => 'Unsupported Bank'],
                ],
                'nationalities' => [
                    'Saudi', 'Egyptian', 'Syrian', 'Jordanian', 'Lebanese', 
                    'Palestinian', 'Iraqi', 'Yemeni', 'Kuwaiti', 'Emirati', 
                    'Bahraini', 'Qatari', 'Omani', 'Other'
                ],
            ],
        ];
    }
}
