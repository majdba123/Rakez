<?php

namespace App\Http\Requests\registartion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUser extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->route('id'),
            'phone' => 'sometimes|string|max:20',
            'password' => 'sometimes|string|min:6',
            'type' => 'sometimes|integer|between:0,8',
            'is_manager' => 'nullable|boolean',
            // Profile fields
            'team' => 'sometimes|string|max:255',
            'identity_number' => 'sometimes|string|max:100|unique:users,identity_number,' . $this->route('id'),
            'birthday' => 'sometimes|date',
            'date_of_works' => 'sometimes|date',
            'contract_type' => 'sometimes|string|max:100',
            'iban' => 'sometimes|string|max:34',
            'salary' => 'sometimes|numeric|min:0',
            'marital_status' => 'sometimes|string|in:single,married,divorced,widowed',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.string' => 'الاسم يجب أن يكون نصاً',
            'name.max' => 'الاسم يجب ألا يتجاوز 255 حرفاً',
            'email.email' => 'يجب إدخال بريد إلكتروني صحيح',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً',
            'phone.string' => 'رقم الهاتف يجب أن يكون نصاً',
            'phone.max' => 'رقم الهاتف يجب ألا يتجاوز 20 رقماً',
            'password.min' => 'كلمة المرور يجب أن تكون على الأقل 6 أحرف',
            'type.integer' => 'نوع المستخدم يجب أن يكون رقماً',
            'type.between' => 'نوع المستخدم يجب أن يكون بين 0 و 7',
            'is_manager.boolean' => 'قيمة المدير يجب أن تكون صحيحة أو خاطئة',
        ];
    }
}
