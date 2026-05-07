<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class ConvertWaitingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.waiting_list.convert');
    }

    public function rules(): array
    {
        return [
            'contract_date' => 'required|date',
            'reservation_type' => 'required|in:confirmed_reservation,negotiation',
            'negotiation_notes' => 'required_if:reservation_type,negotiation|nullable|string',
            'client_nationality' => 'required|string|max:100',
            'client_iban' => 'required|string|max:100',
            'payment_method' => 'required|in:bank_transfer,cash,bank_financing',
            'down_payment_amount' => 'required|numeric|min:0',
            'down_payment_status' => 'required|in:refundable,non_refundable',
            'purchase_mechanism' => 'required|in:cash,supported_bank,unsupported_bank',
        ];
    }

    public function messages(): array
    {
        return [
            'contract_date.required' => 'Contract date is required',
            'reservation_type.required' => 'Reservation type is required',
            'reservation_type.in' => 'Invalid reservation type',
            'negotiation_notes.required_if' => 'Negotiation notes are required for negotiation type',
            'client_nationality.required' => 'Client nationality is required',
            'client_iban.required' => 'Client IBAN is required',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method',
            'down_payment_amount.required' => 'Down payment amount is required',
            'down_payment_amount.numeric' => 'Down payment amount must be a number',
            'down_payment_status.required' => 'Down payment status is required',
            'purchase_mechanism.required' => 'Purchase mechanism is required',
        ];
    }
}
