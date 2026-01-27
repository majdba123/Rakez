<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Validator;

class ContextValidator
{
    public function __construct(
        private readonly SectionRegistry $sectionRegistry
    ) {}

    public function validate(?string $sectionKey, array $context): array
    {
        $schema = $this->sectionRegistry->contextSchema($sectionKey);

        if (empty($schema)) {
            return $context;
        }

        $rules = [];
        foreach ($schema as $param => $rule) {
            $rules[$param] = $this->parseRule($rule);
        }

        $validator = Validator::make($context, $rules);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid context parameters: ' . $validator->errors()->first());
        }

        return $validator->validated();
    }

    private function parseRule(string $rule): array|string
    {
        // Parse Laravel validation rules like "int|min:1"
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
