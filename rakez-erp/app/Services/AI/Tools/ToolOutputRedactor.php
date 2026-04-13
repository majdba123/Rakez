<?php

namespace App\Services\AI\Tools;

/**
 * Central outbound PII redactor for tool responses.
 *
 * Applied to all tool output BEFORE it enters LLM context or is returned
 * through AI orchestration. Complements AiIndexingService (secrets) and
 * RedactPiiFromAi middleware (inbound user input).
 *
 * Design: stateless, deterministic, testable. Works on the JSON string
 * that the orchestrator passes to redactSecrets(), so it can be chained.
 */
class ToolOutputRedactor
{
    /**
     * PII patterns to redact from outbound tool output.
     *
     * These mirror RedactPiiFromAi patterns plus cover additional
     * fields that appear in structured tool responses.
     */
    private const PII_PATTERNS = [
        // Saudi mobile numbers (05xxxxxxxx)
        '/\b(05\d{8})\b/' => '[REDACTED_PHONE]',
        // International phone with country code
        '/(\+966\d{9})/' => '[REDACTED_PHONE]',
        // Email addresses
        '/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/' => '[REDACTED_EMAIL]',
    ];

    /**
     * Keys whose values should be fully replaced regardless of content.
     * Applied when parsing structured JSON objects inside tool output.
     */
    private const SENSITIVE_KEYS = [
        'phone',
        'mobile',
        'client_mobile',
        'contact_info',
        'email',
        'client_email',
        'whatsapp',
    ];

    /**
     * Redact PII from a tool output JSON string.
     *
     * Attempts structured (key-aware) redaction first. Falls back to
     * regex-only redaction if the string is not valid JSON.
     */
    public function redact(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        // Try structured redaction on JSON payloads
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $redacted = $this->redactArray($decoded);

            return json_encode($redacted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $text;
        }

        // Fallback: regex-only for non-JSON strings
        return $this->redactString($text);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(str_replace(['-', ' '], '_', (string) $key));

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true) && is_string($value) && $value !== '') {
                $data[$key] = '[REDACTED_' . strtoupper($normalizedKey) . ']';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactArray($value);

                continue;
            }

            if (is_string($value)) {
                $data[$key] = $this->redactString($value);
            }
        }

        return $data;
    }

    private function redactString(string $value): string
    {
        foreach (self::PII_PATTERNS as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }
}
