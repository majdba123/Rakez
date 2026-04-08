<?php

namespace App\Services\Pdf;

use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\SecondPartyData;
use Carbon\Carbon;

/**
 * Builds JSON payload for frontend PDF generation (عقد حصري — ملء قالب).
 * All values are display-ready (Arabic labels, formatted dates).
 */
class ContractPdfDataService
{
    private const ARABIC_DAYS = [
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
        7 => 'الأحد',
    ];

    private const COMMISSION_FROM_LABELS = [
        'owner' => 'المالك',
        'buyer' => 'المشتري',
    ];

    /** Safe string for PDF: null, empty string, or whitespace → — */
    public function pdfStr(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_string($value)) {
            $t = trim($value);

            return $t === '' ? '—' : $value;
        }
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '—';
    }

    /** Integer-like for PDF (keeps 0). */
    public function pdfInt(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_numeric($value)) {
            return (string) (int) $value;
        }

        return '—';
    }

    /** Date/datetime from DB string, Carbon, or null. */
    public function pdfDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value)) {
            $t = trim($value);
            if ($t === '') {
                return '—';
            }
            try {
                return Carbon::parse($t)->format('Y-m-d');
            } catch (\Throwable) {
                return $t;
            }
        }
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return '—';
        }
    }

    public function pdfDecimal(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_numeric($value)) {
            return number_format((float) $value, $decimals, '.', ',');
        }

        return '—';
    }

    /**
     * @return array<string, string> Display-ready fields for contract_infos PDF rows.
     */
    public function formatContractInfoForPdf(ContractInfo $info): array
    {
        $attrs = $info->getAttributes();

        return [
            'info_record_id' => $this->pdfStr($attrs['id'] ?? $info->getKey() ?? null),
            'contract_id' => $this->pdfStr($attrs['contract_id'] ?? $info->contract_id ?? null),
            'contract_number' => $this->pdfStr($attrs['contract_number'] ?? $info->contract_number ?? null),
            'first_party_name' => $this->pdfStr($attrs['first_party_name'] ?? $info->first_party_name ?? null),
            'first_party_cr_number' => $this->pdfStr($attrs['first_party_cr_number'] ?? $info->first_party_cr_number ?? null),
            'first_party_signatory' => $this->pdfStr($attrs['first_party_signatory'] ?? $info->first_party_signatory ?? null),
            'first_party_phone' => $this->pdfStr($attrs['first_party_phone'] ?? $info->first_party_phone ?? null),
            'first_party_email' => $this->pdfStr($attrs['first_party_email'] ?? $info->first_party_email ?? null),
            'second_party_name' => $this->pdfStr($attrs['second_party_name'] ?? $info->second_party_name ?? null),
            'second_party_address' => $this->pdfStr($attrs['second_party_address'] ?? $info->second_party_address ?? null),
            'second_party_cr_number' => $this->pdfStr($attrs['second_party_cr_number'] ?? $info->second_party_cr_number ?? null),
            'second_party_id_number' => $this->pdfStr($attrs['second_party_id_number'] ?? $info->second_party_id_number ?? null),
            'second_party_signatory' => $this->pdfStr($attrs['second_party_signatory'] ?? $info->second_party_signatory ?? null),
            'second_party_role' => $this->pdfStr($attrs['second_party_role'] ?? $info->second_party_role ?? null),
            'second_party_phone' => $this->pdfStr($attrs['second_party_phone'] ?? $info->second_party_phone ?? null),
            'second_party_email' => $this->pdfStr($attrs['second_party_email'] ?? $info->second_party_email ?? null),
            'hijri_date' => $this->pdfStr($attrs['hijri_date'] ?? $info->hijri_date ?? null),
            'contract_city' => $this->pdfStr($attrs['contract_city'] ?? $info->contract_city ?? null),
            'location_url' => $this->pdfStr($attrs['location_url'] ?? $info->location_url ?? null),
            'agency_number' => $this->pdfStr($attrs['agency_number'] ?? $info->agency_number ?? null),
            'gregorian_date' => $this->pdfDate($attrs['gregorian_date'] ?? $info->gregorian_date ?? null),
            'agency_date' => $this->pdfDate($attrs['agency_date'] ?? $info->agency_date ?? null),
            'release_date' => $this->pdfDate($attrs['release_date'] ?? $info->release_date ?? null),
            'agreement_duration_days' => $this->pdfInt($attrs['agreement_duration_days'] ?? $info->agreement_duration_days ?? null),
            'agreement_duration_months' => $this->pdfInt($attrs['agreement_duration_months'] ?? $info->agreement_duration_months ?? null),
            'avg_property_value' => $this->pdfDecimal($attrs['avg_property_value'] ?? $info->avg_property_value ?? null),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function formatContractMainForPdf(Contract $contract): array
    {
        $a = $contract->getAttributes();

        return [
            'project_name' => $this->pdfStr($a['project_name'] ?? $contract->project_name ?? null),
            'developer_name' => $this->pdfStr($a['developer_name'] ?? $contract->developer_name ?? null),
            'developer_number' => $this->pdfStr($a['developer_number'] ?? $contract->developer_number ?? null),
            'code' => $this->pdfStr($a['code'] ?? $contract->code ?? null),
            'developer_requiment' => $this->pdfStr($a['developer_requiment'] ?? $contract->developer_requiment ?? null),
            'notes' => $this->pdfStr($a['notes'] ?? $contract->notes ?? null),
            'city' => $this->pdfStr($contract->city?->name ?? null),
            'district' => $this->pdfStr($contract->district?->name ?? null),
            'commission_percent' => $contract->commission_percent !== null
                ? $this->pdfStr($contract->commission_percent) . '%'
                : '—',
            'owner_name' => $this->pdfStr($contract->user?->name ?? null),
        ];
    }

    public function getFillData(Contract $contract): array
    {
        $contract->loadMissing(['info', 'contractUnits', 'city', 'district']);
        $info = $contract->info;
        $spd = $contract->secondPartyData;

        $gregorianDate = $info?->gregorian_date
            ? Carbon::parse($info->gregorian_date)
            : ($contract->created_at ?? now());
        $dayOfWeek = (int) $gregorianDate->format('N'); // 1=Mon .. 7=Sun
        $contractDay = self::ARABIC_DAYS[$dayOfWeek] ?? '';

        $unitsCount = $this->resolveUnitsCount($contract, $info);
        $agreementMonths = $info?->agreement_duration_months ?? null;
        if ($agreementMonths === null && $info && $info->agreement_duration_days) {
            $agreementMonths = (string) max(1, (int) floor($info->agreement_duration_days / 30));
        }
        $agreementMonths = $agreementMonths !== null ? (string) $agreementMonths : '';

        $commissionFrom = $info?->commission_from ?? $contract->commission_from ?? 'owner';
        $commissionLabel = self::COMMISSION_FROM_LABELS[$commissionFrom] ?? $commissionFrom;
        $commissionPercent = $info?->commission_percent ?? $contract->commission_percent;
        $commissionPercentStr = $commissionPercent !== null ? (string) $commissionPercent : '';

        $payload = [
            'units_count' => (string) $unitsCount,
            'district' => (string) ($contract->district?->name ?? ''),
            'side' => (string) ($contract->side ?? ''),
            'contract_type' => (string) ($contract->contract_type ?? ''),
            'unit_type' => $this->resolveUnitType($contract),
            'project_name' => (string) ($contract->project_name ?? ''),
            'gregorian_date' => $gregorianDate->format('d-m-Y'),
            'hijri_date' => (string) ($info->hijri_date ?? ''),
            'contract_day' => $contractDay,
            'contract_city' => (string) ($info->contract_city ?? $contract->city?->name ?? ''),
            'second_party_cr_number' => (string) ($info->second_party_cr_number ?? ''),
            'second_party_id' => (string) ($info->second_party_id_number ?? ''),
            'second_party_name' => (string) ($info->second_party_name ?? ''),
            'second_party_address' => (string) ($info->second_party_address ?? ''),
            'second_party_signatory' => (string) ($info->second_party_signatory ?? ''),
            'second_party_role' => (string) ($info->second_party_role ?? ''),
            'second_party_phone' => (string) ($info->second_party_phone ?? ''),
            'agreement_duration_months' => $agreementMonths,
            'commission_from' => $commissionLabel,
            'commission_percent' => $commissionPercentStr,
        ];
        if ($info && $info->agreement_duration_days !== null) {
            $payload['agreement_duration_days'] = (int) $info->agreement_duration_days;
        }
        return $payload;
    }

    private function resolveUnitsCount(Contract $contract, $info): int
    {
        if ($info && isset($info->units_count) && $info->units_count !== null) {
            return (int) $info->units_count;
        }
        if ($contract->relationLoaded('contractUnits') ? $contract->contractUnits->isNotEmpty() : $contract->contractUnits()->exists()) {
            return $contract->contractUnits()->count();
        }
        $legacyUnits = $contract->getAttribute('units');
        return is_array($legacyUnits) ? count($legacyUnits) : 0;
    }

    private function resolveUnitType(Contract $contract): string
    {
        $contract->loadMissing('contractUnits');
        if ($contract->contractUnits->isNotEmpty()) {
            $types = $contract->contractUnits->pluck('unit_type')->unique()->filter()->values()->all();
            return implode('، ', $types);
        }
        $legacyUnits = $contract->getAttribute('units');
        if (is_array($legacyUnits) && count($legacyUnits) > 0) {
            $types = collect($legacyUnits)->pluck('type')->unique()->filter()->values()->all();
            return implode('، ', $types);
        }
        return '';
    }

    /**
     * View data for server-generated contract PDF (عرض العقد — عربي).
     */
    public function buildShowPdfPayload(Contract $contract): array
    {
        $contract = $contract->fresh([
            'user',
            'info',
            'secondPartyData',
            'contractUnits',
            'city',
            'district',
        ]);

        if (!$contract) {
            throw new \RuntimeException('Contract not found');
        }

        $this->normalizeContractUnitsAttribute($contract);

        $commissionFrom = $contract->commission_from ?? 'owner';
        $commissionFromAr = self::COMMISSION_FROM_LABELS[$commissionFrom] ?? (string) $commissionFrom;

        $payload = [
            'contract' => $contract,
            'contract_main' => $this->formatContractMainForPdf($contract),
            'generated_at' => now()->format('Y-m-d H:i'),
            'status_label_ar' => match ((string) ($contract->status ?? '')) {
                'pending' => 'قيد الانتظار',
                'approved' => 'معتمد',
                'rejected' => 'مرفوض',
                'completed' => 'مكتمل',
                default => $contract->status ? (string) $contract->status : '—',
            },
            'side_label_ar' => match ((string) ($contract->side ?? '')) {
                'N' => 'شمال',
                'S' => 'جنوب',
                'E' => 'شرق',
                'W' => 'غرب',
                default => $contract->side ? (string) $contract->side : '—',
            },
            'contract_type_ar' => match ((string) ($contract->contract_type ?? '')) {
                'sale' => 'بيع',
                'rent' => 'إيجار',
                default => $contract->contract_type ? (string) $contract->contract_type : '—',
            },
            'commission_from_ar' => $commissionFromAr,
        ];

        $infoRow = ContractInfo::query()->where('contract_id', $contract->id)->first();
        if ($infoRow) {
            $payload['info_display'] = $this->formatContractInfoForPdf($infoRow);
        }

        $payload['unit_rows'] = [];
        foreach ($contract->contractUnits as $u) {
            $num = $u->getAttribute('unit_number');
            $unitNo = ($num !== null && $num !== '') ? $num : (string) $u->id;
            $areaRaw = $u->getAttribute('area');
            $totalRaw = $u->getAttribute('total_area_m2');
            $areaDisp = ($areaRaw !== null && $areaRaw !== '') ? $areaRaw : (($totalRaw !== null && $totalRaw !== '') ? $totalRaw : null);

            $payload['unit_rows'][] = [
                'unit_number' => $this->pdfStr($unitNo),
                'unit_type' => $this->pdfStr($u->getAttribute('unit_type')),
                'status' => $this->pdfStr($u->getAttribute('status')),
                'price' => $this->pdfDecimal($u->getAttribute('price'), 2),
                'area' => $this->pdfStr($areaDisp),
            ];
        }

        $payload['legacy_unit_rows'] = [];
        $units = $contract->getAttribute('units');
        if (is_array($units)) {
            foreach ($units as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $payload['legacy_unit_rows'][] = [
                    'type' => $this->pdfStr($row['type'] ?? null),
                    'count' => $this->pdfInt($row['count'] ?? null),
                    'price' => isset($row['price']) && is_numeric($row['price'])
                        ? $this->pdfDecimal($row['price'], 2)
                        : '—',
                ];
            }
        }

        if ($contract->secondPartyData) {
            $spd = $contract->secondPartyData;
            $payload['second_party_flags'] = [
                'real_estate_papers_url' => $this->pdfUrlPresent($spd->getAttribute('real_estate_papers_url')),
                'plans_equipment_docs_url' => $this->pdfUrlPresent($spd->getAttribute('plans_equipment_docs_url')),
                'project_logo_url' => $this->pdfUrlPresent($spd->getAttribute('project_logo_url')),
                'prices_units_url' => $this->pdfUrlPresent($spd->getAttribute('prices_units_url')),
                'marketing_license_url' => $this->pdfUrlPresent($spd->getAttribute('marketing_license_url')),
                'advertiser_section_url' => $this->pdfStr($spd->getAttribute('advertiser_section_url')),
            ];
        }

        return $payload;
    }

    private function pdfUrlPresent(mixed $url): string
    {
        if ($url === null || ! is_string($url)) {
            return '—';
        }

        return trim($url) === '' ? '—' : 'متوفر (رابط)';
    }

    /**
     * Decode legacy JSON string in `units` when cast did not apply.
     */
    private function normalizeContractUnitsAttribute(Contract $contract): void
    {
        $raw = $contract->getAttributes()['units'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return;
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $contract->setAttribute('units', $decoded);
        }
    }

    /**
     * View data for PDF: {@see ContractInfo} row only (no parent Contract fields).
     *
     * @throws \RuntimeException when record missing
     */
    public function buildContractInfoOnlyPdfPayload(ContractInfo $info): array
    {
        $fresh = $info->fresh();
        if (!$fresh) {
            throw new \RuntimeException('لا توجد بيانات معلومات العقد');
        }

        return [
            'info' => $fresh,
            'info_display' => $this->formatContractInfoForPdf($fresh),
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function formatSecondPartyDataForPdf(SecondPartyData $spd): array
    {
        $a = $spd->getAttributes();

        return [
            'record_id' => $this->pdfStr($a['id'] ?? $spd->getKey()),
            'contract_id' => $this->pdfStr($a['contract_id'] ?? $spd->contract_id),
            'real_estate_papers_url' => $this->pdfStr($a['real_estate_papers_url'] ?? $spd->real_estate_papers_url),
            'plans_equipment_docs_url' => $this->pdfStr($a['plans_equipment_docs_url'] ?? $spd->plans_equipment_docs_url),
            'project_logo_url' => $this->pdfStr($a['project_logo_url'] ?? $spd->project_logo_url),
            'prices_units_url' => $this->pdfStr($a['prices_units_url'] ?? $spd->prices_units_url),
            'marketing_license_url' => $this->pdfStr($a['marketing_license_url'] ?? $spd->marketing_license_url),
            'advertiser_section_url' => $this->pdfStr($a['advertiser_section_url'] ?? $spd->advertiser_section_url),
            'processed_by' => $this->pdfStr($a['processed_by'] ?? $spd->processed_by),
            'processed_by_name' => $this->pdfStr($spd->processedByUser?->name),
            'processed_at' => $this->pdfDate($a['processed_at'] ?? $spd->processed_at ?? null),
        ];
    }

    /**
     * View data for PDF: {@see SecondPartyData} row only.
     *
     * @throws \RuntimeException when record missing
     */
    public function buildSecondPartyDataOnlyPdfPayload(SecondPartyData $spd): array
    {
        $fresh = $spd->fresh();
        if (!$fresh) {
            throw new \RuntimeException('لا توجد بيانات الطرف الثاني');
        }

        $fresh->loadMissing('processedByUser');

        return [
            'spd' => $fresh,
            'display' => $this->formatSecondPartyDataForPdf($fresh),
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }
}
