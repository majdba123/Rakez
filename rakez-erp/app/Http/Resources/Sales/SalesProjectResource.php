<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SalesProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeAssignment = $this->salesProjectAssignments
            ? $this->salesProjectAssignments->first(fn($a) => $a->isActive())
            : null;

        $teamName = $activeAssignment?->leader?->team
            ?? $this->user?->team
            ?? 'N/A';

        $salesStatus = $this->sales_status ?? 'pending';
        $contractStatus = $this->status;
        $isReady = $contractStatus === 'completed';
        $totalUnits = (int) ($this->total_units ?? 0);
        $availableUnits = (int) ($this->available_units ?? 0);
        $reservedUnits = (int) ($this->reserved_units ?? 0);
        $soldUnits = max(0, $totalUnits - $availableUnits - $reservedUnits);
        $soldUnitsPercent = $totalUnits > 0 ? (int) round(($soldUnits / $totalUnits) * 100) : 0;

        $cardFields = $this->relationLoaded('secondPartyData')
            ? self::cardFieldsFromContract($this->resource, $salesStatus)
            : [
                'status_badge_ar' => $salesStatus === 'available' ? 'متاح' : 'غير متاح',
                'price_min' => null,
                'price_max' => null,
                'area_min_m2' => null,
                'area_max_m2' => null,
                'bedrooms_min' => null,
                'bedrooms_max' => null,
                'unit_type_label_ar' => null,
                'ad_code' => null,
            ];

        return array_merge([
            'contract_id' => $this->id,
            'project_name' => $this->project_name,
            'team_name' => $teamName,
            'project_description' => $this->notes,
            'project_image_url' => self::fullImageUrl($this->project_image_url),
            'location' => trim(($this->city?->name ?? '') . ', ' . ($this->district?->name ?? ''), ', '),
            'city' => $this->city?->name,
            'district' => $this->district?->name,
            'contract_status' => $contractStatus,
            'is_ready' => $isReady,
            'sales_status' => $salesStatus,
            'project_status_label_ar' => self::arabicStatusLabel($salesStatus, $isReady),
            'total_units' => $totalUnits,
            'available_units' => $availableUnits,
            'reserved_units' => $reservedUnits,
            'sold_units' => $soldUnits,
            'sold_units_percent' => $soldUnitsPercent,
            'sold_units_label_ar' => 'وحدة مباعة',
            'preparation_progress_percent' => 0,
            'preparation_progress_label_ar' => 'N/A',
            'remaining_days' => $this->remaining_days,
            'created_at' => $this->created_at?->toIso8601String(),
        ], $cardFields);
    }

    /**
     * Derive listing-card fields from contract (secondPartyData + contractUnits).
     * Returns status_badge_ar, price_min, price_max, area_min_m2, area_max_m2, bedrooms_min, bedrooms_max, unit_type_label_ar, ad_code.
     */
    public static function cardFieldsFromContract($contract, string $salesStatus): array
    {
        $statusBadge = $salesStatus === 'available' ? 'متاح' : 'غير متاح';

        $spd = $contract->secondPartyData ?? null;
        $units = $spd && $spd->relationLoaded('contractUnits') ? $spd->contractUnits : collect();

        $priceMin = null;
        $priceMax = null;
        $areaMin = null;
        $areaMax = null;
        $bedroomsMin = null;
        $bedroomsMax = null;
        $unitTypeLabel = null;

        if ($units->isNotEmpty()) {
            $prices = $units->pluck('price')->filter()->map(fn ($p) => (float) $p);
            $priceMin = $prices->isEmpty() ? null : round($prices->min(), 2);
            $priceMax = $prices->isEmpty() ? null : round($prices->max(), 2);

            // Area: use area or total_area_m2 as fallback
            $areas = $units->map(function ($u) {
                $a = $u->area !== null && $u->area !== '' ? (float) $u->area : null;
                return $a ?? $u->total_area_m2;
            })->filter();
            $areaMin = $areas->isEmpty() ? null : round($areas->min(), 2);
            $areaMax = $areas->isEmpty() ? null : round($areas->max(), 2);

            $bedrooms = $units->pluck('bedrooms')->filter()->map(fn ($b) => (int) $b);
            $bedroomsMin = $bedrooms->isEmpty() ? null : $bedrooms->min();
            $bedroomsMax = $bedrooms->isEmpty() ? null : $bedrooms->max();

            $firstType = $units->pluck('unit_type')->filter()->first();
            $unitTypeLabel = $firstType ? (string) $firstType : null;
        }

        $adCode = $spd ? $spd->advertiser_section_url : null;

        return [
            'status_badge_ar' => $statusBadge,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'area_min_m2' => $areaMin,
            'area_max_m2' => $areaMax,
            'bedrooms_min' => $bedroomsMin,
            'bedrooms_max' => $bedroomsMax,
            'unit_type_label_ar' => $unitTypeLabel,
            'ad_code' => $adCode,
        ];
    }

    /**
     * Return full URL for an image (absolute URLs unchanged, relative paths prefixed with app URL).
     */
    public static function fullImageUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }
        return url($url);
    }

    public static function arabicStatusLabel(string $salesStatus, bool $isReady): string
    {
        if (!$isReady) {
            return 'غير جاهز - تتبع الأوراق';
        }

        return $salesStatus === 'available' ? 'جاهز - متاح للبيع' : 'قيد الانتظار';
    }
}
