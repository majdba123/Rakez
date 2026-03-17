<?php

namespace App\Services\AI;

class AiIndexingService
{
    /**
     * Regex patterns for secrets and sensitive data that must be redacted.
     */
    private const SECRET_PATTERNS = [
        // OpenAI API keys
        '/sk-[a-zA-Z0-9_-]{20,}/' => '[REDACTED_API_KEY]',
        // Generic API keys / tokens
        '/\b(api[_-]?key|token|secret|password|bearer)\s*[:=]\s*["\']?[a-zA-Z0-9_\-\.]{16,}["\']?/i' => '[REDACTED_CREDENTIAL]',
        // Bearer tokens in headers
        '/Bearer\s+[a-zA-Z0-9_\-\.]{20,}/i' => 'Bearer [REDACTED_TOKEN]',
        // AWS keys
        '/AKIA[0-9A-Z]{16}/' => '[REDACTED_AWS_KEY]',
        // Private keys
        '/-----BEGIN\s+(RSA\s+)?PRIVATE KEY-----[\s\S]*?-----END\s+(RSA\s+)?PRIVATE KEY-----/' => '[REDACTED_PRIVATE_KEY]',
        // Database connection strings
        '/mysql:\/\/[^\s]+/i' => '[REDACTED_DB_URL]',
        '/postgres(ql)?:\/\/[^\s]+/i' => '[REDACTED_DB_URL]',
        // Saudi National IDs (10 digits starting with 1 or 2)
        '/\b[12]\d{9}\b/' => '[REDACTED_NATIONAL_ID]',
        // Saudi IBANs
        '/\bSA\d{22}\b/' => '[REDACTED_IBAN]',
    ];

    /**
     * Redact secrets and sensitive data from text.
     */
    public function redactSecrets(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        foreach (self::SECRET_PATTERNS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }
}
