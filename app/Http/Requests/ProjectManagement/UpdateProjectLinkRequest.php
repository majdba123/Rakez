<?php

namespace App\Http\Requests\ProjectManagement;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = Contract::find($this->route('id'));
        return $contract && $this->user()?->can('update', $contract);
    }

    public function rules(): array
    {
        return [
            'project_link' => ['nullable', 'string', 'url', 'max:2048'],
        ];
    }
}
