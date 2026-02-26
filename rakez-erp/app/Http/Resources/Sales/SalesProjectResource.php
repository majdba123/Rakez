<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        $isReady = in_array($contractStatus, ['ready', 'approved']);
        $totalUnits = (int) ($this->total_units ?? 0);
        $availableUnits = (int) ($this->available_units ?? 0);
        $reservedUnits = (int) ($this->reserved_units ?? 0);
        $soldUnits = max(0, $totalUnits - $availableUnits - $reservedUnits);
        $soldUnitsPercent = $totalUnits > 0 ? (int) round(($soldUnits / $totalUnits) * 100) : 0;

        return [
            'contract_id' => $this->id,
            'project_name' => $this->project_name,
            'team_name' => $teamName,
            'project_description' => $this->notes,
            'location' => "{$this->city}, {$this->district}",
            'city' => $this->city,
            'district' => $this->district,
            'contract_status' => $contractStatus,
            'is_ready' => $isReady,
            'sales_status' => $salesStatus,
            'project_status_label_ar' => self::arabicStatusLabel($salesStatus, $isReady),
            'total_units' => $totalUnits,
            'available_units' => $availableUnits,
            'reserved_units' => $reservedUnits,
            'sold_units' => $soldUnits,
            'sold_units_percent' => $soldUnitsPercent,
            'preparation_progress_percent' => 0,
            'preparation_progress_label_ar' => 'N/A',
            'remaining_days' => $this->remaining_days,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public static function arabicStatusLabel(string $salesStatus, bool $isReady): string
    {
        if (!$isReady) {
            return 'غير جاهز - تتبع الأوراق';
        }

        return $salesStatus === 'available' ? 'جاهز - متاح للبيع' : 'قيد الانتظار';
    }
}
