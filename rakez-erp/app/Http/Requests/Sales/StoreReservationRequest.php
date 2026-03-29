<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ContractUnit;
use App\Models\Contract;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.reservations.create');
    }

    /**
     * Map frontend values to API: reservation_type (عقد/contract -> confirmed_reservation), phone -> client_mobile, and defaults for optional-in-UI fields.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // reservation_type: واجهة قد ترسل "عقد" أو "contract" أو "حجز بغرض التفاوض" أو "تفاوض" أو "negotiation" أو كائن { value, label }
        $type = $input['reservation_type'] ?? $input['reservationType'] ?? null;
        if (is_array($type) || (is_object($type) && !$type instanceof \Stringable)) {
            $type = $type['value'] ?? $type['id'] ?? $type['label'] ?? null;
        }
        if (is_string($type)) {
            $t = trim($type);
            $tLower = strtolower($t);
            // حجز بغرض التفاوض / تفاوض / negotiation
            if (str_contains($t, 'تفاوض') || in_array($tLower, ['تفاوض', 'negotiation', 'under_negotiation'], true)
                || str_contains($tLower, 'negotiat')) {
                $input['reservation_type'] = 'negotiation';
            } elseif (str_contains($t, 'عقد') || in_array($tLower, ['عقد', 'contract', 'confirmed', 'confirmed_reservation'], true)
                || str_contains($tLower, 'contract') || str_contains($tLower, 'confirm')) {
                $input['reservation_type'] = 'confirmed_reservation';
            }
        }

        // رقم الجوال من phone أو mobile
        if (empty($input['client_mobile']) && !empty($input['phone'])) {
            $input['client_mobile'] = $input['phone'];
        }
        if (empty($input['client_mobile']) && !empty($input['mobile'])) {
            $input['client_mobile'] = $input['mobile'];
        }

        // مبلغ العربون
        if (!isset($input['down_payment_amount']) || $input['down_payment_amount'] === '' || $input['down_payment_amount'] === null) {
            if (isset($input['downPaymentAmount']) && $input['downPaymentAmount'] !== '') {
                $input['down_payment_amount'] = $input['downPaymentAmount'];
            }
        }

        // قيم افتراضية لحقول قد لا يرسلها نموذج إنشاء حجز مبسط
        if (empty($input['contract_date']) && empty($input['contractDate'])) {
            $input['contract_date'] = now()->format('Y-m-d');
        }
        if ((empty($input['client_nationality']) && empty($input['clientNationality']))) {
            $input['client_nationality'] = 'غير محدد';
        }
        $iban = $input['client_iban'] ?? $input['clientIban'] ?? null;
        if ($iban === null || $iban === '') {
            $input['client_iban'] = '-';
        }
        if (empty($input['payment_method']) && empty($input['paymentMethod'])) {
            $input['payment_method'] = $input['payment_method'] ?? 'cash';
        }
        // دعم القيم العربية من نموذج الحجز
        if (!empty($input['payment_method']) && is_string($input['payment_method'])) {
            $pm = $input['payment_method'];
            if (str_contains($pm, 'تحويل') || $pm === 'bank_transfer') {
                $input['payment_method'] = 'bank_transfer';
            } elseif (str_contains($pm, 'كاش') || $pm === 'نقد') {
                $input['payment_method'] = 'cash';
            } elseif (str_contains($pm, 'تمويل') || $pm === 'bank_financing') {
                $input['payment_method'] = 'bank_financing';
            }
        }
        if (empty($input['down_payment_status']) && empty($input['downPaymentStatus'])) {
            $input['down_payment_status'] = $input['down_payment_status'] ?? 'refundable';
        }
        if (!empty($input['down_payment_status']) && is_string($input['down_payment_status'])) {
            $ds = $input['down_payment_status'];
            if (str_contains($ds, 'مسترد') && !str_contains($ds, 'غير')) {
                $input['down_payment_status'] = 'refundable';
            } elseif (str_contains($ds, 'غير') || str_contains($ds, 'non')) {
                $input['down_payment_status'] = 'non_refundable';
            }
        }
        if (empty($input['purchase_mechanism']) && empty($input['purchaseMechanism'])) {
            $input['purchase_mechanism'] = $input['purchase_mechanism'] ?? 'cash';
        }
        if (!empty($input['purchase_mechanism']) && is_string($input['purchase_mechanism'])) {
            $mech = $input['purchase_mechanism'];
            if (str_contains($mech, 'غير مدعوم') || $mech === 'unsupported_bank') {
                $input['purchase_mechanism'] = 'unsupported_bank';
            } elseif ((str_contains($mech, 'مدعوم') && !str_contains($mech, 'غير')) || $mech === 'supported_bank') {
                $input['purchase_mechanism'] = 'supported_bank';
            } elseif (str_contains($mech, 'كاش') || $mech === 'cash') {
                $input['purchase_mechanism'] = 'cash';
            }
        }

        // عند نوع الحجز "تفاوض": قيم افتراضية للحقول الإلزامية إن لم يرسلها النموذج
        if (isset($input['reservation_type']) && $input['reservation_type'] === 'negotiation') {
            if (empty($input['negotiation_notes']) && !empty($input['negotiationNotes'])) {
                $input['negotiation_notes'] = $input['negotiationNotes'];
            }
            if (empty($input['negotiation_notes'])) {
                $input['negotiation_notes'] = '-';
            }
            if (empty($input['negotiation_reason']) && !empty($input['negotiationReason'])) {
                $input['negotiation_reason'] = $input['negotiationReason'];
            }
            if (empty($input['negotiation_reason'])) {
                $input['negotiation_reason'] = 'السعر';
            }
            if ((!isset($input['proposed_price']) || $input['proposed_price'] === '' || $input['proposed_price'] === null)
                && isset($input['proposedPrice']) && $input['proposedPrice'] !== '') {
                $input['proposed_price'] = $input['proposedPrice'];
            }
        }

        $this->merge($input);
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'contract_unit_id' => 'required|exists:contract_units,id',
            'contract_date' => 'required|date',
            'reservation_type' => 'required|in:confirmed_reservation,negotiation',
            'negotiation_notes' => 'required_if:reservation_type,negotiation|nullable|string',
            'negotiation_reason' => 'required_if:reservation_type,negotiation|nullable|string|max:255',
            'proposed_price' => 'required_if:reservation_type,negotiation|nullable|numeric|min:0',
            'evacuation_date' => 'nullable|date|after_or_equal:today',
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
                $unit = ContractUnit::with('contract')->find($this->contract_unit_id);
                if ($unit && (int) $unit->contract_id !== (int) $this->contract_id) {
                    $validator->errors()->add('contract_unit_id', 'Unit does not belong to this project');
                }

                // Validate proposed_price is less than unit price for negotiation
                if ($this->reservation_type === 'negotiation' && $this->proposed_price && $unit) {
                    if ((float) $this->proposed_price >= (float) $unit->price) {
                        $validator->errors()->add('proposed_price', 'Proposed price must be less than the original unit price');
                    }
                }
            }

            // Validate evacuation_date is required for off-plan projects with confirmed deposit
            if ($this->contract_id) {
                $contract = Contract::find($this->contract_id);
                if ($contract && $contract->is_off_plan 
                    && $this->down_payment_status === 'non_refundable' 
                    && empty($this->evacuation_date)) {
                    $validator->errors()->add('evacuation_date', 'Evacuation date is required for off-plan projects with confirmed deposit');
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
            'negotiation_reason.required_if' => 'Negotiation reason is required when reservation type is negotiation',
            'proposed_price.required_if' => 'Proposed price is required when reservation type is negotiation',
            'proposed_price.min' => 'Proposed price must be greater than 0',
            'evacuation_date.after_or_equal' => 'Evacuation date must be today or in the future',
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
