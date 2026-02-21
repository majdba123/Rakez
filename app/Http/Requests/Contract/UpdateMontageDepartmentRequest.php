<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

/**
 * قسم المونتاج - Update Montage Department Request
 */
class UpdateMontageDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_url' => 'nullable|url|max:500',
            'video_url' => 'nullable|url|max:500',
            'description' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'image_url.url' => 'رابط الصورة يجب أن يكون رابط صحيح',
            'image_url.max' => 'رابط الصورة يجب أن لا يتجاوز 500 حرف',
            'video_url.url' => 'رابط الفيديو يجب أن يكون رابط صحيح',
            'video_url.max' => 'رابط الفيديو يجب أن لا يتجاوز 500 حرف',
            'description.max' => 'الوصف يجب أن لا يتجاوز 2000 حرف',
        ];
    }
}

