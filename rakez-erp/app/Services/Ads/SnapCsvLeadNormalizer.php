<?php

namespace App\Services\Ads;

/**
 * Normalizes Snap-exported (or generic) CSV lead rows to the platform leads schema.
 */
final class SnapCsvLeadNormalizer
{
    /**
     * Map common CSV header names (case-insensitive) to our schema keys.
     */
    private const COLUMN_MAP = [
        'lead_id' => ['lead_id', 'id', 'lead id'],
        'name' => ['name', 'full_name', 'full name', 'customer name', 'contact name'],
        'email' => ['email', 'email address', 'e-mail'],
        'phone' => ['phone', 'phone_number', 'phone number', 'mobile', 'telephone'],
        'form_id' => ['form_id', 'form id', 'form'],
        'ad_id' => ['ad_id', 'ad id'],
        'adset_id' => ['adset_id', 'adset id', 'ad set id'],
        'campaign_id' => ['campaign_id', 'campaign id', 'campaign'],
        'created_time' => ['created_time', 'created time', 'date', 'submitted', 'created_at', 'timestamp'],
    ];

    /**
     * Parse CSV content and return normalized lead rows for platform=snap.
     *
     * @return array<int, array{platform: string, lead_id: string, name: string, email: string, phone: string, form_id: string, ad_id: string, adset_id: string, campaign_id: string, created_time: string, extra_data: array}>
     */
    public function normalizeCsv(string $csvContent): array
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $csvContent);
        rewind($stream);

        $headerRow = fgetcsv($stream);
        if ($headerRow === false || empty($headerRow)) {
            fclose($stream);

            return [];
        }
        $headerRow = array_map('trim', $headerRow);
        $headerMap = $this->buildHeaderMap($headerRow);

        $leads = [];
        $index = 0;
        while (($values = fgetcsv($stream)) !== false) {
            if (empty($values)) {
                continue;
            }
            $row = [];
            $extra = [];
            foreach ($headerRow as $i => $col) {
                $value = trim($values[$i] ?? '');
                $key = $headerMap[$i] ?? null;
                if ($key !== null) {
                    $row[$key] = $value;
                } else {
                    $extra[$col] = $value;
                }
            }
            $index++;
            $leads[] = [
                'platform' => 'snap',
                'lead_id' => (string) ($row['lead_id'] ?? (string) $index),
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'form_id' => (string) ($row['form_id'] ?? ''),
                'ad_id' => (string) ($row['ad_id'] ?? ''),
                'adset_id' => (string) ($row['adset_id'] ?? ''),
                'campaign_id' => (string) ($row['campaign_id'] ?? ''),
                'created_time' => (string) ($row['created_time'] ?? ''),
                'extra_data' => $extra,
            ];
        }
        fclose($stream);

        return $leads;
    }

    /**
     * @param  array<int, string>  $headerRow
     * @return array<int, string>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $i => $header) {
            $normalized = strtolower(trim($header));
            foreach (self::COLUMN_MAP as $schemaKey => $aliases) {
                if (in_array($normalized, $aliases, true) || $normalized === $schemaKey) {
                    $map[$i] = $schemaKey;
                    break;
                }
            }
        }

        return $map;
    }
}
