<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class UploadContractUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'ملف CSV مطلوب',
            'csv_file.file' => 'يجب أن يكون ملف صحيح',
            'csv_file.mimes' => 'يجب أن يكون الملف من نوع CSV',
            'csv_file.max' => 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت',
        ];
    }
}

