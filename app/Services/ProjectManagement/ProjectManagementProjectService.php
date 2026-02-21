<?php

namespace App\Services\ProjectManagement;

use App\Models\Contract;
use App\Models\ContractPreparationStage;
use App\Services\Marketing\MarketingProjectService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectManagementProjectService
{
    public const SEGMENT_UNREADY = 'unready';
    public const SEGMENT_READY = 'ready_for_marketing';
    public const SEGMENT_ARCHIVE = 'archive';

    public function __construct(
        private MarketingProjectService $marketingProjectService
    ) {}

    /**
     * Get project counts per segment for the tabs.
     *
     * @return array{unready: int, ready_for_marketing: int, archive: int}
     */
    public function getSegmentCounts(?string $teamId = null, ?string $search = null): array
    {
        $baseQuery = function ($query) use ($teamId, $search) {
            if ($teamId) {
                $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('project_name', 'like', '%' . addslashes($search) . '%')
                        ->orWhere('city', 'like', '%' . addslashes($search) . '%')
                        ->orWhere('district', 'like', '%' . addslashes($search) . '%');
                });
            }
        };

        $unready = Contract::query()
            ->whereNull('deleted_at')
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->when($teamId || $search, $baseQuery)
            ->count();

        $ready = Contract::query()
            ->whereNull('deleted_at')
            ->where('status', 'ready')
            ->when($teamId || $search, $baseQuery)
            ->count();

        $archive = (int) (
            Contract::onlyTrashed()->when($teamId || $search, $baseQuery)->count() +
            Contract::whereNull('deleted_at')->where('status', 'completed')
                ->when($teamId || $search, $baseQuery)->count()
        );

        return [
            'unready' => $unready,
            'ready_for_marketing' => $ready,
            'archive' => $archive,
        ];
    }

    /**
     * Get projects for a segment with pagination.
     */
    public function getProjectsBySegment(
        string $segment,
        int $perPage = 15,
        ?string $teamId = null,
        ?string $search = null,
        int $page = 1
    ): LengthAwarePaginator {
        $query = Contract::with([
            'preparationStages',
            'info',
            'projectMedia',
            'teams',
            'secondPartyData.contractUnits',
        ])->orderBy('updated_at', 'desc');

        if ($teamId) {
            $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('project_name', 'like', '%' . addslashes($search) . '%')
                    ->orWhere('city', 'like', '%' . addslashes($search) . '%')
                    ->orWhere('district', 'like', '%' . addslashes($search) . '%');
            });
        }

        switch ($segment) {
            case self::SEGMENT_UNREADY:
                $query->whereNull('deleted_at')->whereIn('status', ['pending', 'approved', 'rejected']);
                break;
            case self::SEGMENT_READY:
                $query->whereNull('deleted_at')->where('status', 'ready');
                break;
            case self::SEGMENT_ARCHIVE:
                $query->withTrashed()->where(function ($q) {
                    $q->whereNotNull('deleted_at') // trashed
                        ->orWhere('status', 'completed');
                });
                break;
            default:
                $query->whereNull('deleted_at');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get duration status label for UI (e.g. "خلال 6 أيام", "انتهت المهلة").
     */
    public function getDurationStatusLabel(int $contractId): array
    {
        $arr = $this->marketingProjectService->getContractDurationStatus($contractId);
        $days = $arr['days'] ?? 0;
        if ($days < 0) {
            return [
                'label' => 'انتهت المهلة',
                'days' => $days,
                'status' => 'ended',
            ];
        }
        if ($days === 0) {
            return [
                'label' => 'آخر يوم',
                'days' => 0,
                'status' => 'last_day',
            ];
        }
        return [
            'label' => 'خلال ' . $days . ' أيام',
            'days' => $days,
            'status' => $arr['status'] ?? 'active',
        ];
    }

    /**
     * Preparation progress (0-100) from completed stages count.
     */
    public function getPreparationProgressPercent(Contract $contract): int
    {
        $count = $contract->preparationStages->whereNotNull('completed_at')->count();
        return (int) round(($count / ContractPreparationStage::TOTAL_STAGES) * 100);
    }

    /**
     * Units sold percent: sold units / total units * 100.
     * Sold = units with status not 'available' (e.g. reserved/sold) or from reservations.
     */
    public function getUnitsSoldPercent(Contract $contract): int
    {
        $secondParty = $contract->secondPartyData;
        if (!$secondParty) {
            return 0;
        }
        $units = $secondParty->contractUnits ?? $secondParty->contractUnits()->get();
        $total = $units->count();
        if ($total === 0) {
            return 0;
        }
        $sold = $units->whereIn('status', ['sold', 'reserved', 'pending'])->count();
        return (int) round(($sold / $total) * 100);
    }
}
