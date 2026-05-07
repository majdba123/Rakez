<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('use-ai-assistant');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,txt,md,docx'],
            'title' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'يجب إرفاق ملف.',
            'file.max' => 'الحد الأقصى لحجم الملف 10 ميجابايت.',
            'file.mimes' => 'الأنواع المسموحة: PDF, TXT, MD, DOCX.',
        ];
    }
}
