<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadProjectBoardImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'kind' => ['nullable', 'string', Rule::in(['board_image', 'signboard_image'])],
            'note' => 'nullable|string|max:1000',
        ];
    }
}
