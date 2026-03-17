<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlatformLeadsExport implements FromCollection, WithHeadings
{
    /**
     * Normalized lead rows: each row is an array with keys
     * platform, lead_id, name, phone, email, form_id, ad_id, campaign_id, created_time, and optional extra keys.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly array $rows
    ) {}

    public function collection(): \Illuminate\Support\Collection
    {
        return collect($this->rows)->map(fn (array $row): array => [
            $row['platform'] ?? '',
            $row['lead_id'] ?? '',
            $row['name'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['form_id'] ?? '',
            $row['ad_id'] ?? '',
            $row['adset_id'] ?? '',
            $row['campaign_id'] ?? '',
            $row['created_time'] ?? '',
            isset($row['extra_data']) && is_array($row['extra_data'])
                ? json_encode($row['extra_data'], JSON_UNESCAPED_UNICODE)
                : (string) ($row['extra_data'] ?? ''),
        ]);
    }

    public function headings(): array
    {
        return [
            'platform',
            'lead_id',
            'name',
            'email',
            'phone',
            'form_id',
            'ad_id',
            'adset_id',
            'campaign_id',
            'created_time',
            'extra_data',
        ];
    }
}
