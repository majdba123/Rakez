<?php

namespace App\Http\Resources\Contract;

use App\Http\Resources\Shared\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unitCount = 0;
        $totalPrice = 0.0;
        $hasRealUnits = $this->relationLoaded('contractUnits');

        if ($hasRealUnits && $this->contractUnits->isNotEmpty()) {
            $unitCount = $this->contractUnits->count();
            $totalPrice = (float) $this->contractUnits->sum('price');
        } elseif (is_array($this->units) && count($this->units) > 0) {
            foreach ($this->units as $unit) {
                $count = (int) ($unit['count'] ?? 0);
                $price = (float) ($unit['price'] ?? 0);
                $unitCount += $count;
                $totalPrice += ($count * $price);
            }
        }

        $projectProgress = $this->buildProjectProgress($unitCount);
        $advertiserNumber = $this->getAdvertiserNumber();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_name' => $this->project_name,
            'developer_name' => $this->developer_name,
            'developer_number' => $this->developer_number,
            'city_id' => $this->city_id,
            'district_id' => $this->district_id,
            'is_off_plan' => $this->is_off_plan,
            'city' => $this->city?->name,
            'district' => $this->district?->name,
            'side' => $this->side,
            'contract_type' => $this->contract_type,
            'code' => $this->code,
            'developer_requiment' => $this->developer_requiment,
            'project_image_url' => $this->project_image_url,
            'status' => $this->status,
            'is_complete_second' => (bool) $this->is_complete_second,
            'notes' => $this->notes,
            'units' => $this->units ?? [],
            'unit_count' => $unitCount,
            'total_price' => $totalPrice,
            'commission_percent' => $this->commission_percent !== null ? (float) $this->commission_percent : null,
            'commission_from' => $this->commission_from,
            'advertiser_number' => $advertiserNumber,
            'advertiser_number_source' => $this->getAdvertiserNumberSource(),
            'advertiser_number_expires_at' => $this->getAdvertiserNumberExpiresAt()?->toDateString(),
            'advertiser_number_remaining_days' => $this->getAdvertiserNumberRemainingDays(),
            'advertiser_number_expiry_status' => $this->getAdvertiserNumberExpiryStatus(),
            'archive_requested_at' => $this->archive_requested_at?->toIso8601String(),
            'archive_confirmed_at' => $this->archive_confirmed_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'archive_note' => $this->archive_note,
            'boards_removed_confirmed_at' => $this->boards_removed_confirmed_at?->toIso8601String(),
            'can_confirm_archive' => $this->canConfirmArchive(),
            'archive' => [
                'requested_at' => $this->archive_requested_at?->toIso8601String(),
                'requested_by' => $this->archiveUserPayload('archiveRequestedByUser'),
                'confirmed_at' => $this->archive_confirmed_at?->toIso8601String(),
                'confirmed_by' => $this->archiveUserPayload('archiveConfirmedByUser'),
                'archived_at' => $this->archived_at?->toIso8601String(),
                'archived_by' => $this->archiveUserPayload('archivedByUser'),
                'boards_removed_confirmed_at' => $this->boards_removed_confirmed_at?->toIso8601String(),
                'boards_removed_confirmed_by' => $this->archiveUserPayload('boardsRemovedConfirmedByUser'),
                'note' => $this->archive_note,
                'can_confirm' => $this->canConfirmArchive(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'project_progress' => $projectProgress,
            'user' => new UserResource($this->whenLoaded('user')),
            'info' => new ContractInfoResource($this->whenLoaded('info')),
            'second_party_data' => new SecondPartyDataResource($this->whenLoaded('secondPartyData')),
            'contract_units' => $this->when($hasRealUnits, fn () => ContractUnitResource::collection($this->contractUnits)),
            'photography_department' => new PhotographyDepartmentResource($this->whenLoaded('photographyDepartment')),
            'boards_department' => new BoardsDepartmentResource($this->whenLoaded('boardsDepartment')),
            'montage_department' => new MontageDepartmentResource($this->whenLoaded('montageDepartment')),
            'project_media' => $this->when($this->relationLoaded('projectMedia'), fn () => ProjectMediaResource::collection($this->projectMedia)),
            'board_media' => $this->when($this->relationLoaded('projectMedia'), fn () => ProjectMediaResource::collection($this->projectMedia->where('department', 'boards')->values())),
        ];
    }

    protected function buildProjectProgress(int $unitCount): array
    {
        $spd = $this->secondPartyData;
        $filled = fn (?string $v) => $v !== null && trim((string) $v) !== '';

        $step1 = $spd && $filled($spd->real_estate_papers_url) && $filled($spd->marketing_license_url);
        $step2 = $spd && $filled($spd->plans_equipment_docs_url);
        $step3 = $spd && $filled($spd->project_logo_url);
        $step4 = $this->relationLoaded('info') && $this->info !== null;
        $step5 = $spd && $filled($spd->prices_units_url) && $unitCount > 0;
        $step6 = false;
        $step7 = $spd && $filled($spd->advertiser_section_url);

        $steps = [
            ['step_number' => 1, 'label_ar' => 'الصكوك والرخصة', 'label_en' => 'Deeds and License', 'completed' => $step1],
            ['step_number' => 2, 'label_ar' => 'المخططات والتصميمات', 'label_en' => 'Plans and Designs', 'completed' => $step2],
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

    protected function archiveUserPayload(string $relation): ?array
    {
        $user = $this->{$relation};

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'type' => $user->type,
        ];
    }
}
