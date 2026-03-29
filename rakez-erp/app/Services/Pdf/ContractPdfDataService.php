<?php

namespace App\Services\Pdf;

use App\Models\Contract;
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
}
