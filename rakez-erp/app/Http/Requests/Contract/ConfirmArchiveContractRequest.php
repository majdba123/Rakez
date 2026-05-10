<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmArchiveContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'boards_removed_confirmed' => 'required|accepted',
            'archive_note' => 'nullable|string|max:1000',
        ];
    }
}
