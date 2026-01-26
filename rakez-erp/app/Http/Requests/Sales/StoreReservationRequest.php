<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ContractUnit;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.reservations.create');
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'contract_unit_id' => 'required|exists:contract_units,id',
            'contract_date' => 'required|date',
            'reservation_type' => 'required|in:confirmed_reservation,negotiation',
            'negotiation_notes' => 'required_if:reservation_type,negotiation|nullable|string',
            'client_name' => 'required|string|max:255',
            'client_mobile' => 'required|string|max:50',
            'client_nationality' => 'required|string|max:100',
            'client_iban' => 'required|string|max:100',
            'payment_method' => 'required|in:bank_transfer,cash,bank_financing',
            'down_payment_amount' => 'required|numeric|min:0',
            'down_payment_status' => 'required|in:refundable,non_refundable',
            'purchase_mechanism' => 'required|in:cash,supported_bank,unsupported_bank',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation: unit belongs to contract
            if ($this->contract_unit_id && $this->contract_id) {
                $unit = ContractUnit::with('secondPartyData.contract')->find($this->contract_unit_id);
                if ($unit && $unit->secondPartyData && $unit->secondPartyData->contract_id != $this->contract_id) {
                    $validator->errors()->add('contract_unit_id', 'Unit does not belong to this project');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'contract_id.required' => 'Project is required',
            'contract_unit_id.required' => 'Unit is required',
            'reservation_type.required' => 'Reservation type is required',
            'negotiation_notes.required_if' => 'Negotiation notes are required when reservation type is negotiation',
            'client_name.required' => 'Client name is required',
            'client_mobile.required' => 'Client mobile is required',
            'client_nationality.required' => 'Client nationality is required',
            'client_iban.required' => 'Client IBAN is required',
            'payment_method.required' => 'Payment method is required',
            'down_payment_amount.required' => 'Down payment amount is required',
            'down_payment_status.required' => 'Down payment status is required',
            'purchase_mechanism.required' => 'Purchase mechanism is required',
        ];
    }
}
