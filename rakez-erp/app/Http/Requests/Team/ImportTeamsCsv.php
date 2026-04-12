<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ImportTeamsCsv extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'CSV file is required.',
            'file.file'     => 'The upload must be a valid file.',
            'file.mimes'    => 'The file must be CSV (.csv) or Excel (.xlsx, .xls).',
            'file.max'      => 'The file must not exceed 5 MB.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }
}
