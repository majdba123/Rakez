<?php

namespace App\Http\Resources\Contract;

use App\Http\Resources\Shared\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unitCount = 0;
        $totalPrice = 0.0;

        if (is_array($this->units) && count($this->units) > 0) {
            foreach ($this->units as $unit) {
                $count = (int) ($unit['count'] ?? 0);
                $price = (float) ($unit['price'] ?? 0);
                $unitCount += $count;
                $totalPrice += ($count * $price);
            }
        }

        $advertiserNumber = $this->getAdvertiserNumber();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_name' => $this->project_name,
            'developer_name' => $this->developer_name,
            'developer_number' => $this->developer_number,
            'city_id' => $this->city_id,
            'district_id' => $this->district_id,
            'city' => $this->city?->name,
            'district' => $this->district?->name,
            'side' => $this->side,
            'contract_type' => $this->contract_type,
            'is_off_plan' => $this->is_off_plan,
            'code' => $this->code,
            'unit_count' => $unitCount,
            'total_price' => (float) $totalPrice,
            'status' => $this->status,
            'is_complete_second' => (bool) $this->is_complete_second,
            'developer_requiment' => $this->developer_requiment,
            'has_photography_data' => $this->photographyDepartment ? 1 : 0,
            'has_montage_data' => $this->montageDepartment ? 1 : 0,
            'advertiser_section_url' => $this->relationLoaded('secondPartyData')
                && ($url = $this->secondPartyData?->advertiser_section_url) !== null
                && trim((string) $url) !== '' ? 1 : 0,
            'advertiser_number' => $advertiserNumber,
            'advertiser_number_source' => $this->getAdvertiserNumberSource(),
            'advertiser_number_expires_at' => $this->getAdvertiserNumberExpiresAt()?->toDateString(),
            'advertiser_number_remaining_days' => $this->getAdvertiserNumberRemainingDays(),
            'advertiser_number_expiry_status' => $this->getAdvertiserNumberExpiryStatus(),
            'archive_requested_at' => $this->archive_requested_at?->toIso8601String(),
            'archive_requested_by' => $this->archiveUserPayload('archiveRequestedByUser'),
            'archive_confirmed_at' => $this->archive_confirmed_at?->toIso8601String(),
            'archive_confirmed_by' => $this->archiveUserPayload('archiveConfirmedByUser'),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'archived_by' => $this->archiveUserPayload('archivedByUser'),
            'archive_note' => $this->archive_note,
            'boards_removed_confirmed_at' => $this->boards_removed_confirmed_at?->toIso8601String(),
            'boards_removed_confirmed_by' => $this->archiveUserPayload('boardsRemovedConfirmedByUser'),
            'can_confirm_archive' => $this->canConfirmArchive(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'info' => new ContractInfoResource($this->whenLoaded('info')),
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
