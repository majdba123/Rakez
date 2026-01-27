<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReservationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reservation_id' => $this->id,
            'contract_id' => $this->contract_id,
            'contract_unit_id' => $this->contract_unit_id,
            'marketing_employee_id' => $this->marketing_employee_id,
            'marketing_employee_name' => $this->marketingEmployee->name ?? 'N/A',
            'status' => $this->status,
            'reservation_type' => $this->reservation_type,
            'contract_date' => $this->contract_date->format('Y-m-d'),
            'negotiation_notes' => $this->negotiation_notes,
            'client_name' => $this->client_name,
            'client_mobile' => $this->client_mobile,
            'client_nationality' => $this->client_nationality,
            'client_iban' => $this->client_iban,
            'payment_method' => $this->payment_method,
            'down_payment_amount' => (float) $this->down_payment_amount,
            'down_payment_status' => $this->down_payment_status,
            'purchase_mechanism' => $this->purchase_mechanism,
            'voucher_url' => $this->voucher_pdf_path ? "/api/sales/reservations/{$this->id}/voucher" : null,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
