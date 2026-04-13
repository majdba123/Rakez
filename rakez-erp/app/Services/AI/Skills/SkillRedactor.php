<?php

namespace App\Services\AI\Skills;

class SkillRedactor
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function apply(array $payload, string $profile = 'none'): array
    {
        if ($profile === 'none') {
            return $payload;
        }

        return $this->redactArray($payload, $profile);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redactArray(array $data, string $profile): array
    {
        $sensitiveKeys = $this->sensitiveKeysForProfile($profile);

        foreach ($data as $key => $value) {
            if (in_array((string) $key, $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactArray($value, $profile);
                continue;
            }

            if (is_string($value)) {
                $data[$key] = $this->redactString($value, $profile);
            }
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function sensitiveKeysForProfile(string $profile): array
    {
        return match ($profile) {
            'finance_sensitive' => ['iban', 'client_iban', 'account_number', 'bank_account', 'national_id'],
            'credit_sensitive' => ['iban', 'client_iban', 'national_id', 'client_mobile', 'contact_info'],
            'pii_basic' => ['email', 'phone', 'mobile', 'national_id', 'iban', 'client_iban', 'contact_info'],
            default => [],
        };
    }

    private function redactString(string $value, string $profile): string
    {
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $value) ?? $value;

        if (in_array($profile, ['pii_basic', 'credit_sensitive', 'finance_sensitive'], true)) {
            $value = preg_replace('/(?<!\w)\+?(?:966|0)?5\d{8}\b/', '[REDACTED_PHONE]', $value) ?? $value;
            $value = preg_replace('/\bSA\d{22}\b/i', '[REDACTED_IBAN]', $value) ?? $value;
            $value = preg_replace('/\b\d{10}\b/', '[REDACTED_NATIONAL_ID]', $value) ?? $value;
        }

        return $value;
    }
}
