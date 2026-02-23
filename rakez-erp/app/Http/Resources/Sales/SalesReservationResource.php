<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reservation_id' => $this->id,
            'project_name' => $this->contract->project_name ?? 'N/A',
            'unit_number' => $this->contractUnit->unit_number ?? 'N/A',
            'client_name' => $this->client_name,
            'status' => $this->status,
            'reservation_type' => $this->reservation_type,
            'marketing_employee_id' => $this->marketing_employee_id,
            'marketing_employee_name' => $this->marketingEmployee->name ?? 'N/A',
            'down_payment_amount' => (float) $this->down_payment_amount,
            'contract_date' => $this->contract_date->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'voucher_url' => $this->voucher_pdf_path ? "/api/sales/reservations/{$this->id}/voucher" : null,
        ];
    }
}
