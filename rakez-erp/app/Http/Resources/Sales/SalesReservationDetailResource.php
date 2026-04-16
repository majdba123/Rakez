<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SalesReservationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reservation_id' => $this->id,
            'contract_id' => $this->contract_id,
            'contract_unit_id' => $this->contract_unit_id,
            'project_name' => $this->contract->project_name ?? null,
            'unit_number' => $this->contractUnit->unit_number ?? null,
            'marketing_employee_id' => $this->marketing_employee_id,
            'marketing_employee_name' => $this->marketingEmployee->name ?? null,
            'status' => $this->status,
            'reservation_type' => $this->reservation_type,
            'contract_date' => $this->contract_date->format('Y-m-d'),
            'negotiation_notes' => $this->negotiation_notes,
            'negotiation_reason' => $this->negotiation_reason,
            'proposed_price' => $this->proposed_price !== null ? (float) $this->proposed_price : null,
            'client_name' => $this->client_name,
            'client_mobile' => $this->client_mobile ?? null,
            'client_nationality' => $this->client_nationality ?? null,
            'client_iban' => $this->client_iban ?? null,
            'payment_method' => $this->payment_method ?? null,
            'down_payment_amount' => (float) $this->down_payment_amount,
            'down_payment_status' => $this->down_payment_status ?? null,
            'purchase_mechanism' => $this->purchase_mechanism ?? null,
            'evacuation_date' => $this->evacuation_date?->format('Y-m-d'),
            'voucher_url' => $this->voucher_pdf_path ? "/api/sales/reservations/{$this->id}/voucher" : null,
            'receipt_voucher_path' => $this->receipt_voucher_path,
            'receipt_voucher_url' => $this->receipt_voucher_path
                ? url('/storage/' . ltrim($this->receipt_voucher_path, '/'))
                : null,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
