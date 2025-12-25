<?php

namespace App\Http\Requests\registartion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterUser extends FormRequest
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
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'type' => 'required|integer|in:0,1,2,3,4,5,6,7',
            // Profile fields
            'team' => 'nullable|string|max:255',
            'identity_number' => 'nullable|string|max:100|unique:users,identity_number',
            'birthday' => 'nullable|date',
            'date_of_works' => 'nullable|date',
            'contract_type' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:34',
            'salary' => 'nullable|numeric|min:0',
            'marital_status' => 'nullable|string|in:single,married,divorced,widowed',
        ];



        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'Email has already been taken.',
            'phone.unique' => 'Phone has already been taken.',
            'type.required' => 'User type is required.',
            'type.in' => 'User type must be one of the accepted values.',
            'identity_number.unique' => 'Identity number has already been taken.',
            'identity_date.date' => 'Identity date must be a valid date.',
            'birthday.date' => 'Birthday must be a valid date.',
            'date_of_works.date' => 'Date of works must be a valid date.',
            'salary.numeric' => 'Salary must be a valid number.',
            'marital_status.in' => 'Marital status must be one of: single, married, divorced, widowed.',

        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }

}
