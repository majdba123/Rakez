<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Shared\UserResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Prefer real contract_units (CSV/table) when loaded; fallback to legacy units JSON
        $unitCount = 0;
        $totalPrice = 0.0;
        $hasRealUnits = $this->relationLoaded('secondPartyData')
            && $this->secondPartyData
            && $this->secondPartyData->relationLoaded('contractUnits');

        if ($hasRealUnits && $this->secondPartyData->contractUnits->isNotEmpty()) {
            $unitCount = $this->secondPartyData->contractUnits->count();
            $totalPrice = (float) $this->secondPartyData->contractUnits->sum('price');
        } elseif (is_array($this->units) && count($this->units) > 0) {
            foreach ($this->units as $unit) {
                $count = (int) ($unit['count'] ?? 0);
                $price = (float) ($unit['price'] ?? 0);
                $unitCount += $count;
                $totalPrice += ($count * $price);
            }
        }

        $projectProgress = $this->buildProjectProgress($unitCount);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_name' => $this->project_name,
            'developer_name' => $this->developer_name,
            'developer_number' => $this->developer_number,
            'city' => $this->city,
            'district' => $this->district,
            'developer_requiment' => $this->developer_requiment,
            'project_image_url' => $this->project_image_url,
            'status' => $this->status,
            'notes' => $this->notes,
            'units' => $this->units ?? [],
            'unit_count' => $unitCount,
            'total_price' => $totalPrice,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // متتبع حالة المشروع (Project Status Tracker) – للواجهة والـ API
            'project_progress' => $projectProgress,

            // Relations
            'user' => new UserResource($this->whenLoaded('user')),
            'info' => new ContractInfoResource($this->whenLoaded('info')),
            'second_party_data' => new SecondPartyDataResource($this->whenLoaded('secondPartyData')),
            // Real contract units (CSV/table) for project-tracker and unit list UIs
            'contract_units' => $this->when($hasRealUnits, function () {
                return ContractUnitResource::collection($this->secondPartyData->contractUnits);
            }),
            'photography_department' => new PhotographyDepartmentResource($this->whenLoaded('photographyDepartment')),
            'boards_department' => new BoardsDepartmentResource($this->whenLoaded('boardsDepartment')),
            'montage_department' => new MontageDepartmentResource($this->whenLoaded('montageDepartment')),
        ];
    }

    /**
     * Build project progress (7 steps) for متتبع حالة المشروع.
     * Based on SecondPartyData URLs, contract info, and units.
     */
    protected function buildProjectProgress(int $unitCount): array
    {
        $spd = $this->secondPartyData;
        $filled = fn(?string $v) => $v !== null && trim((string) $v) !== '';

        $step1 = $spd && $filled($spd->real_estate_papers_url) && $filled($spd->marketing_license_url);
        $step2 = $spd && $filled($spd->plans_equipment_docs_url);
        $step3 = $spd && $filled($spd->project_logo_url);
        $step4 = $this->relationLoaded('info') && $this->info !== null;
        $step5 = $spd && $filled($spd->prices_units_url) && $unitCount > 0;
        $step6 = false; // الضمانات وأخرى – لا يوجد حقل حالياً
        $step7 = $spd && $filled($spd->advertiser_section_url);

        $steps = [
            ['step_number' => 1, 'label_ar' => 'الصكوك والرخصة', 'label_en' => 'Deeds and License', 'completed' => $step1],
            ['step_number' => 2, 'label_ar' => 'المخطاطات والتصميمات', 'label_en' => 'Plans and Designs', 'completed' => $step2],
            ['step_number' => 3, 'label_ar' => 'السجل والهوية', 'label_en' => 'Registry and Identity', 'completed' => $step3],
            ['step_number' => 4, 'label_ar' => 'شهادة اتمام وأخرى', 'label_en' => 'Completion Certificate and Others', 'completed' => $step4],
            ['step_number' => 5, 'label_ar' => 'الاسعار والوحدات', 'label_en' => 'Prices and Units', 'completed' => $step5],
            ['step_number' => 6, 'label_ar' => 'الضمانات وأخرى', 'label_en' => 'Warranties and Others', 'completed' => $step6],
            ['step_number' => 7, 'label_ar' => 'رقم المعلن', 'label_en' => 'Advertiser Number', 'completed' => $step7],
        ];

        $completedCount = (int) array_sum(array_column($steps, 'completed'));

        return [
            'completed_count' => $completedCount,
            'total_count' => 7,
            'steps' => $steps,
        ];
    }
}
