<?php

namespace App\Http\Requests\registartion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateUser extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('team') && ! $this->has('team_id')) {
            $this->merge([
                'team_id' => $this->input('team'),
            ]);
        }
    }

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
            'type' => [
                'sometimes',
                'integer',
                Rule::in(config('user_types.valid_ids', range(1, 13))),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ((int) $value === 1) {
                        $user = Auth::user();
                        if (! $user || $user->type !== 'admin') {
                            $fail('Only admin can set employee type to admin.');
                        }
                    }
                },
            ],
            'role' => 'nullable|string|exists:roles,name',
            'is_manager' => 'nullable|boolean',
            'team_id' => 'sometimes|integer|exists:teams,id',
            'cv' => 'sometimes|file|mimes:pdf,doc,docx|max:10240',
            'contract' => 'sometimes|file|mimes:pdf,doc,docx|max:10240',
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
            'name.string' => '????? ??? ?? ???? ????',
            'name.max' => '????? ??? ??? ?????? 255 ?????',
            'email.email' => '??? ????? ???? ???????? ????',
            'email.unique' => '?????? ?????????? ???? ??????',
            'phone.string' => '??? ?????? ??? ?? ???? ????',
            'phone.max' => '??? ?????? ??? ??? ?????? 20 ?????',
            'password.min' => '???? ?????? ??? ?? ???? ??? ????? 6 ????',
            'type.integer' => '??? ???????? ??? ?? ???? ?????',
            'type.between' => '??? ???????? ??? ?? ???? ??? 1 ? 13',
            'role.exists' => 'The selected role does not exist.',
            'is_manager.boolean' => '???? ?????? ??? ?? ???? ????? ?? ?????',
        ];
    }
}
