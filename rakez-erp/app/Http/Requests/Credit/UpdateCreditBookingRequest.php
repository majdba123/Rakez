<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCreditBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name' => 'sometimes|nullable|string|max:255',
            'client_mobile' => 'sometimes|nullable|string|max:50',
            'client_nationality' => 'sometimes|nullable|string|max:100',
            'client_iban' => 'sometimes|nullable|string|max:100',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $keys = ['client_name', 'client_mobile', 'client_nationality', 'client_iban'];
            $any = false;
            foreach ($keys as $k) {
                if ($this->has($k)) {
                    $any = true;
                    break;
                }
            }
            if (! $any) {
                $v->errors()->add('client_name', 'يجب إرسال حقل واحد على الأقل من بيانات العميل');
            }
        });
    }
}
