<?php

namespace App\Services\Accounting;

use App\Models\ProjectRewardSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProjectRewardSettingService
{
    /** @var array<int, string> */
    protected static array $decimalFields = [
        'reward_percentage',
        'vat_percentage',
        'assigned_bring_percentage',
        'assigned_convince_percentage',
        'assigned_close_percentage',
        'outside_bring_percentage',
        'outside_convince_percentage',
        'outside_close_percentage',
        'ceo_percentage',
        'sales_manager_percentage',
        'sales_leader_percentage',
        'group_leader_percentage',
        'external_marketer_percentage',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): ProjectRewardSetting
    {
        return DB::transaction(function () use ($data, $user) {
            $payload = $this->sanitizePayload($data);
            unset($payload['created_by']);
            $payload['created_by'] = $user->id;

            if (!array_key_exists('is_active', $payload)) {
                $payload['is_active'] = true;
            }

            $model = ProjectRewardSetting::query()->create($payload);

            if ($model->is_active) {
                $this->deactivateOthersOnContract($model);
            }

            return $model->fresh($this->eagerLoads());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProjectRewardSetting $setting, array $data): ProjectRewardSetting
    {
        return DB::transaction(function () use ($setting, $data) {
            $payload = $this->sanitizePayload(Arr::only($data, (new ProjectRewardSetting)->getFillable()));
            unset($payload['created_by']);

            if ($payload !== []) {
                $setting->fill($payload);
                $setting->save();
            }

            if ($setting->is_active) {
                $this->deactivateOthersOnContract($setting);
            }

            return $setting->fresh($this->eagerLoads());
        });
    }

    public function activate(ProjectRewardSetting $setting): ProjectRewardSetting
    {
        return DB::transaction(function () use ($setting) {
            ProjectRewardSetting::query()
                ->where('contract_id', $setting->contract_id)
                ->whereNull('deleted_at')
                ->update(['is_active' => false]);

            $setting->refresh();
            $setting->is_active = true;
            $setting->save();

            return $setting->fresh($this->eagerLoads());
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function index(array $filters = []): mixed
    {
        $query = ProjectRewardSetting::query()
            ->with($this->eagerLoads())
            ->orderByDesc('id');

        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate(max(1, min(100, $perPage)));
    }

    /**
     * @return array<int, string>
     */
    protected function eagerLoads(): array
    {
        return [
            'contract',
            'creator',
            'ceoUser',
            'salesManagerUser',
            'salesLeaderUser',
            'groupLeaderUser',
            'externalMarketerUser',
        ];
    }

    protected function deactivateOthersOnContract(ProjectRewardSetting $keep): void
    {
        ProjectRewardSetting::query()
            ->where('contract_id', $keep->contract_id)
            ->whereKeyNot($keep->getKey())
            ->whereNull('deleted_at')
            ->update(['is_active' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $data): array
    {
        $keys = (new ProjectRewardSetting)->getFillable();
        $row = Arr::only($data, $keys);

        if (array_key_exists('tax_enabled', $row) && $row['tax_enabled'] === null) {
            $row['tax_enabled'] = false;
        }

        foreach (self::$decimalFields as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            if ($row[$field] === '' || $row[$field] === null) {
                $row[$field] = match ($field) {
                    'reward_percentage' => null,
                    'vat_percentage' => 15,
                    default => 0,
                };
            }
        }

        return $row;
    }
}
