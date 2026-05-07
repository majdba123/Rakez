<?php

namespace App\Http\Requests\registartion;

use App\Models\TeamGroup;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
     * When a team group is chosen, team id must be the parent team of that group (single source of truth).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('team_group_id')) {
            return;
        }

        $group = TeamGroup::query()->find($this->input('team_group_id'));
        if ($group === null) {
            return;
        }

        $this->merge([
            'team' => $group->team_id,
        ]);
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
            'type' => [
                'required',
                'integer',
                Rule::in(config('user_types.valid_ids', range(1, 13))),
                function (string $attribute, mixed $value, \Closure $fail) {
                    // Only admin can create an employee with type=admin (1)
                    if ((int) $value === 1) {
                        $user = Auth::user();
                        if (!$user || $user->type !== 'admin') {
                            $fail('Only admin can create an employee with admin type.');
                        }
                    }
                },
            ],
            'role' => 'nullable|string|exists:roles,name',
            'is_manager' => 'nullable|boolean',
            'is_executive_director' => 'nullable|boolean',
            // Profile: team_group_id defines the sub-group; `team` is merged from the group in prepareForValidation()
            'team_group_id' => [
                'nullable',
                'integer',
                'exists:team_groups,id',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $typeId = (int) $this->input('type');
                    $typeName = config('user_types.numeric_map', [])[$typeId] ?? null;
                    $isManager = filter_var($this->input('is_manager'), FILTER_VALIDATE_BOOL);
                    $isExecutive = filter_var($this->input('is_executive_director'), FILTER_VALIDATE_BOOL);

                    // sales_leader cannot be assigned to a team group
                    if ($typeName === 'sales_leader') {
                        $fail('قائد المبيعات (sales_leader) لا يمكن تعيينه داخل مجموعة فريق (team_group_id).');
                        return;
                    }

                    // sales manager or sales executive-director cannot be assigned to a team group
                    if ($typeName === 'sales' && ($isManager || $isExecutive)) {
                        $fail('موظف المبيعات المدير/المدير التنفيذي لا يمكن تعيينه داخل مجموعة فريق (team_group_id).');
                        return;
                    }

                },
            ],
            'team' => [
                'nullable',
                'integer',
                'exists:teams,id',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $typeId = (int) $this->input('type');
                    $typeName = config('user_types.numeric_map', [])[$typeId] ?? null;
                    if ($typeName !== 'sales_leader') {
                        return;
                    }

                    $exists = User::query()
                        ->where('type', 'sales_leader')
                        ->where('team_id', (int) $value)
                        ->exists();
                    if ($exists) {
                        $fail('لا يمكن تعيين أكثر من قائد مبيعات واحد لنفس الفريق.');
                    }
                },
            ],
            // Employee files
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // max 10MB
            'contract' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // max 10MB
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
            'role.exists' => 'The selected role does not exist.',
            'is_manager.boolean' => 'قيمة المدير يجب أن تكون صحيحة أو خاطئة',
            'is_executive_director.boolean' => 'قيمة المدير التنفيذي يجب أن تكون صحيحة أو خاطئة',
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
