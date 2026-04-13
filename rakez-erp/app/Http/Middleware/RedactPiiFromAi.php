<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedactPiiFromAi
{
    /**
     * PII patterns specific to Saudi Arabia and general usage.
     */
    private const PATTERNS = [
        // Saudi National ID (10 digits starting with 1 or 2)
        '/\b([12]\d{9})\b/' => '[REDACTED_NATIONAL_ID]',
        // Saudi mobile numbers
        '/\b(05\d{8})\b/' => '[REDACTED_PHONE]',
        // International phone with country code (+ is not a word boundary)
        '/(\+966\d{9})/' => '[REDACTED_PHONE]',
        // Saudi IBAN
        '/\b(SA\d{22})\b/' => '[REDACTED_IBAN]',
        // Email addresses
        '/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/' => '[REDACTED_EMAIL]',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Only redact fields that are known to flow into AI-facing prompts.
        $fieldsToRedact = ['message', 'question', 'fallback_text'];

        foreach ($fieldsToRedact as $field) {
            if ($request->has($field)) {
                $request->merge([$field => $this->redactValue($request->input($field))]);
            }
        }

        if ($request->has('context')) {
            $request->merge([
                'context' => $this->redactValue($request->input('context')),
            ]);
        }

        return $next($request);
    }

    /**
     * Redact PII patterns from text.
     */
    public function redact(string $text): string
    {
        foreach (self::PATTERNS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }

    /**
     * @return mixed
     */
    private function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redact($value);
        }

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $redacted[$key] = $this->redactValue($item);
            }

            return $redacted;
        }

        return $value;
    }
}
