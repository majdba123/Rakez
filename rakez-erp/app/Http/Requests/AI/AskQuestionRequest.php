<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AskQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sections = array_keys(config('ai_sections', []));
        $section = $this->input('section');
        $contextRules = $this->getContextRules($section);

        $rules = [
            'question' => ['required', 'string', 'max:2000'],
            'section' => ['nullable', 'string', Rule::in($sections)],
            'context' => ['nullable', 'array'],
        ];

        // Add nested context validation rules
        foreach ($contextRules as $key => $rule) {
            $rules[$key] = $rule;
        }

        return $rules;
    }

    private function getContextRules(?string $section): array
    {
        if (! $section) {
            return [];
        }

        $sections = config('ai_sections', []);
        $sectionConfig = $sections[$section] ?? null;

        if (! $sectionConfig || ! isset($sectionConfig['context_schema'])) {
            return [];
        }

        $rules = [];
        foreach ($sectionConfig['context_schema'] as $param => $rule) {
            $rules["context.{$param}"] = $this->parseValidationRule($rule);
        }

        return $rules;
    }

    private function parseValidationRule(string $rule): array|string
    {
        $parts = explode('|', $rule);
        $parsed = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, ':')) {
                [$key, $value] = explode(':', $part, 2);
                $parsed[] = $key . ':' . $value;
            } else {
                $parsed[] = $part;
            }
        }

        return $parsed;
    }
}
