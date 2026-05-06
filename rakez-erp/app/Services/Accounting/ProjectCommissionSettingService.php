<?php

namespace App\Services\Accounting;

use App\Models\ProjectCommissionSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProjectCommissionSettingService
{
    /** @var array<int, string> */
    protected static array $decimalFinancialFields = [
        'commission_percentage',
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
    public function create(array $data, User $user): ProjectCommissionSetting
    {
        return DB::transaction(function () use ($data, $user) {
            $payload = $this->sanitizePayload($data);
            unset($payload['created_by']);
            $payload['created_by'] = $user->id;
            if (!array_key_exists('is_active', $payload)) {
                $payload['is_active'] = true;
            }

            $model = ProjectCommissionSetting::create($payload);

            if ($model->is_active) {
                $this->deactivateOthersOnProject($model);
            }

            return $model->fresh($this->eagerLoads());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProjectCommissionSetting $setting, array $data): ProjectCommissionSetting
    {
        return DB::transaction(function () use ($setting, $data) {
            $payload = $this->sanitizePayload(Arr::only($data, (new ProjectCommissionSetting)->getFillable()));
            unset($payload['created_by']);

            if ($payload !== []) {
                $setting->fill($payload);
                $setting->save();
            }

            if ($setting->is_active) {
                $this->deactivateOthersOnProject($setting);
            }

            return $setting->fresh($this->eagerLoads());
        });
    }

    public function activate(ProjectCommissionSetting $setting): ProjectCommissionSetting
    {
        return DB::transaction(function () use ($setting) {
            ProjectCommissionSetting::query()
                ->where('project_id', $setting->project_id)
                ->update(['is_active' => false]);

            $setting->refresh();
            $setting->is_active = true;
            $setting->save();

            return $setting->fresh($this->eagerLoads());
        });
    }

    /**
     * Paginated index rows (relationships eager-loaded once per batch).
     */
    public function index(array $filters = []): mixed
    {
        $query = ProjectCommissionSetting::with($this->eagerLoads())
            ->orderByDesc('id');

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
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
            'project',
            'creator',
            'ceoUser',
            'salesManagerUser',
            'salesLeaderUser',
            'groupLeaderUser',
            'externalMarketerUser',
        ];
    }

    /**
     * Turn off other rows for same project — keeps `$keep` active.
     */
    protected function deactivateOthersOnProject(ProjectCommissionSetting $keep): void
    {
        ProjectCommissionSetting::query()
            ->where('project_id', $keep->project_id)
            ->whereKeyNot($keep->getKey())
            ->update(['is_active' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $data): array
    {
        $keys = (new ProjectCommissionSetting)->getFillable();
        $row = Arr::only($data, $keys);

        foreach (self::$decimalFinancialFields as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            if ($row[$field] === '' || $row[$field] === null) {
                $row[$field] = 0;
            }
        }

        return $row;
    }
}
