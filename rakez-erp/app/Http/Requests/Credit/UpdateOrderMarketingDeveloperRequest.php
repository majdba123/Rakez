<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrderMarketingDeveloperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (!$this->filled('developer_name') && $this->filled('name')) {
            $merge['developer_name'] = $this->input('name');
        }
        if (!$this->filled('developer_number') && $this->filled('number')) {
            $merge['developer_number'] = $this->input('number');
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'developer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'developer_number' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $keys = ['developer_name', 'developer_number', 'description', 'location', 'name', 'number'];
            $any = false;
            foreach ($keys as $k) {
                if ($this->has($k)) {
                    $any = true;
                    break;
                }
            }
            if (!$any) {
                $v->errors()->add('developer_name', 'لم يتم إرسال أي حقول للتحديث');
            }
        });
    }
}
