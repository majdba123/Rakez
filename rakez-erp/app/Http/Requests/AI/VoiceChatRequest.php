<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class VoiceChatRequest extends FormRequest
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
            'audio' => array_filter([
                'bail',
                'nullable',
                'file',
                'required_without:fallback_text',
                'max:' . (int) config('ai_voice.upload.max_kb', 12288),
                ! empty(config('ai_voice.upload.allowed_mimetypes', []))
                    ? 'mimetypes:' . implode(',', config('ai_voice.upload.allowed_mimetypes', []))
                    : null,
                ! empty(config('ai_voice.upload.allowed_extensions', []))
                    ? 'extensions:' . implode(',', config('ai_voice.upload.allowed_extensions', []))
                    : null,
            ]),
            'fallback_text' => ['nullable', 'string', 'required_without:audio', 'max:2000'],
            'session_id' => ['nullable', 'uuid'],
            'section' => ['nullable', 'string', Rule::in($sections)],
            'context' => ['nullable', 'array'],
            'with_tts' => ['nullable', 'boolean'],
            'tts_voice' => ['nullable', 'string', Rule::in(config('ai_voice.speech.allowed_voices', []))],
            'tts_format' => ['nullable', 'string', Rule::in(config('ai_voice.speech.allowed_formats', []))],
        ];

        foreach ($contextRules as $key => $rule) {
            $rules[$key] = $rule;
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $fallbackText = $this->input('fallback_text');

        $this->merge([
            'fallback_text' => is_string($fallbackText) ? trim($fallbackText) : $fallbackText,
            'session_id' => is_string($this->input('session_id')) ? trim($this->input('session_id')) : $this->input('session_id'),
            'section' => is_string($this->input('section')) ? trim($this->input('section')) : $this->input('section'),
            'tts_voice' => is_string($this->input('tts_voice')) ? trim($this->input('tts_voice')) : $this->input('tts_voice'),
            'tts_format' => is_string($this->input('tts_format')) ? trim($this->input('tts_format')) : $this->input('tts_format'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $audio = $this->file('audio');
            $fallbackText = $this->input('fallback_text');

            if (! $audio && (! is_string($fallbackText) || trim($fallbackText) === '')) {
                $validator->errors()->add('fallback_text', 'Fallback text is required when audio is not provided.');
            }

            if (! $this->boolean('with_tts')) {
                if ($this->filled('tts_voice')) {
                    $validator->errors()->add('tts_voice', 'TTS voice can only be sent when with_tts is true.');
                }

                if ($this->filled('tts_format')) {
                    $validator->errors()->add('tts_format', 'TTS format can only be sent when with_tts is true.');
                }
            }
        });
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
