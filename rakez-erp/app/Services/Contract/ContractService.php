<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingProject;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class ContractService
{

    public function getContracts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = Contract::with(['photographyDepartment', 'montageDepartment', 'user', 'secondPartyData', 'city', 'district']);

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }

            // Filter by user
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $query->byUser($filters['user_id']);
            }

            // Filter by city (ID)
            if (isset($filters['city_id']) && $filters['city_id'] !== '' && $filters['city_id'] !== null) {
                $query->where('city_id', (int) $filters['city_id']);
            }

            // Filter by district (ID)
            if (isset($filters['district_id']) && $filters['district_id'] !== '' && $filters['district_id'] !== null) {
                $query->where('district_id', (int) $filters['district_id']);
            }

            // Filter by project name (search)
            if (isset($filters['project_name']) && !empty($filters['project_name'])) {
                $query->where('project_name', 'like', '%' . addslashes($filters['project_name']) . '%');
            }

            // Filter by developer name
            if (isset($filters['developer_name']) && !empty($filters['developer_name'])) {
                $query->byDeveloper($filters['developer_name']);
            }

            // Filter by has photography department
            if (isset($filters['has_photography'])) {
                $has = (string) $filters['has_photography'];
                if ($has === '1' || $has === 'true') {
                    $query->whereHas('photographyDepartment');
                } elseif ($has === '0' || $has === 'false') {
                    $query->whereDoesntHave('photographyDepartment');
                }
            }

            // Filter by has montage department
            if (isset($filters['has_montage'])) {
                $has = (string) $filters['has_montage'];
                if ($has === '1' || $has === 'true') {
                    $query->whereHas('montageDepartment');
                } elseif ($has === '0' || $has === 'false') {
                    $query->whereDoesntHave('montageDepartment');
                }
            }

            // Sort by latest
            $query->orderBy('created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch contracts: ' . $e->getMessage());
        }
    }

    /**
     * Get contracts that belong to a specific team (via contract_team pivot),
     * with filters and pagination.
     */
    public function getContractsByTeam(int $teamId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = Contract::with(['photographyDepartment', 'montageDepartment', 'secondPartyData'])
                ->whereHas('teams', function ($q) use ($teamId) {
                    $q->where('teams.id', $teamId);
                });

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }

            $query->orderBy('created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch team contracts: ' . $e->getMessage());
        }
    }

    /**
     * Meta stats for team contracts (based on the same filters, without pagination).
     */
    public function getTeamContractsMeta(int $teamId, array $filters = []): array
    {
        $base = Contract::query()
            ->whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            });

        // Meta is always for ALL contracts of this team (no status filter)

        $total = (clone $base)->count();
        $byStatus = (clone $base)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_contracts' => $total,
            'contracts_by_status' => $byStatus,
        ];
    }

    /**
     * High-performance: get only contract locations (location_url) for a team.
     * Uses joins to avoid heavy eager-loading and large payloads.
     */
    public function getContractLocationsByTeam(int $teamId, ?string $status = null, int $perPage = 200): LengthAwarePaginator
    {
        try {
            $query = Contract::query()
                ->join('contract_team', 'contract_team.contract_id', '=', 'contracts.id')
                ->leftJoin('contract_infos', 'contract_infos.contract_id', '=', 'contracts.id')
                ->where('contract_team.team_id', $teamId)
                ->select([
                    'contracts.id as contract_id',
                    'contracts.project_name',
                    'contracts.status',
                    'contract_infos.location_url',
                    'contracts.created_at',
                ]);

            if ($status) {
                $query->where('contracts.status', $status);
            }

            $query->orderBy('contracts.created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch team contract locations: ' . $e->getMessage());
        }
    }

    /**
     * High-performance: get only contract locations (location_url) across all contracts,
     * with filters equivalent to adminIndex/getContractsForAdmin.
     */
    public function getContractLocationsForAdmin(array $filters = [], int $perPage = 200): LengthAwarePaginator
    {
        try {
            $query = Contract::query()
                ->leftJoin('contract_infos', 'contract_infos.contract_id', '=', 'contracts.id')
                ->select([
                    'contracts.id as contract_id',
                    'contracts.project_name',
                    'contracts.status',
                    'contract_infos.location_url',
                    'contracts.created_at',
                ]);

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->where('contracts.status', $filters['status']);
            }

            // Filter by user
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $query->where('contracts.user_id', $filters['user_id']);
            }

            // Filter by city (ID)
            if (isset($filters['city_id']) && $filters['city_id'] !== '' && $filters['city_id'] !== null) {
                $query->where('contracts.city_id', (int) $filters['city_id']);
            }

            // Filter by district (ID)
            if (isset($filters['district_id']) && $filters['district_id'] !== '' && $filters['district_id'] !== null) {
                $query->where('contracts.district_id', (int) $filters['district_id']);
            }

            // Filter by project name (search)
            if (isset($filters['project_name']) && !empty($filters['project_name'])) {
                $query->where('contracts.project_name', 'like', '%' . addslashes($filters['project_name']) . '%');
            }

            // Filter by has photography department (respect soft deletes)
            if (isset($filters['has_photography'])) {
                $has = (string) $filters['has_photography'];
                if ($has === '1' || $has === 'true') {
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('photography_departments')
                            ->whereColumn('photography_departments.contract_id', 'contracts.id')
                            ->whereNull('photography_departments.deleted_at');
                    });
                } elseif ($has === '0' || $has === 'false') {
                    $query->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('photography_departments')
                            ->whereColumn('photography_departments.contract_id', 'contracts.id')
                            ->whereNull('photography_departments.deleted_at');
                    });
                }
            }

            // Filter by has montage department (respect soft deletes)
            if (isset($filters['has_montage'])) {
                $has = (string) $filters['has_montage'];
                if ($has === '1' || $has === 'true') {
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('montage_departments')
                            ->whereColumn('montage_departments.contract_id', 'contracts.id')
                            ->whereNull('montage_departments.deleted_at');
                    });
                } elseif ($has === '0' || $has === 'false') {
                    $query->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('montage_departments')
                            ->whereColumn('montage_departments.contract_id', 'contracts.id')
                            ->whereNull('montage_departments.deleted_at');
                    });
                }
            }

            $query->orderBy('contracts.created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch contract locations: ' . $e->getMessage());
        }
    }


    /**
     * Inventory/Admin: contract list with only agency_date + location fields and adminIndex-like filters.
     * Returns lightweight rows (no eager-loading).
     */
    public function getContractsAgencyOverviewForAdmin(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        try {
            // LEFT JOIN so we return all contracts; agency_date/location_url can be null when contract_info is missing or agency_date not set
            $query = Contract::query()
                ->leftJoin('contract_infos', function ($join) {
                    $join->on('contract_infos.contract_id', '=', 'contracts.id')
                        ->whereNull('contract_infos.deleted_at');
                })
                ->select([
                    'contracts.id as contract_id',
                    'contracts.project_name',
                    'contracts.status',
                    'contracts.city_id',
                    'contracts.district_id',
                    'contract_infos.agency_date',
                    'contract_infos.location_url',
                    'contracts.created_at',
                ]);

            // Same filter behavior as adminIndex/getContractsForAdmin
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->where('contracts.status', $filters['status']);
            }
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $query->where('contracts.user_id', $filters['user_id']);
            }
            if (isset($filters['city_id']) && $filters['city_id'] !== '' && $filters['city_id'] !== null) {
                $query->where('contracts.city_id', (int) $filters['city_id']);
            }
            if (isset($filters['district_id']) && $filters['district_id'] !== '' && $filters['district_id'] !== null) {
                $query->where('contracts.district_id', (int) $filters['district_id']);
            }
            if (isset($filters['project_name']) && !empty($filters['project_name'])) {
                $query->where('contracts.project_name', 'like', '%' . addslashes($filters['project_name']) . '%');
            }

            if (isset($filters['has_photography'])) {
                $has = (string) $filters['has_photography'];
                if ($has === '1' || $has === 'true') {
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('photography_departments')
                            ->whereColumn('photography_departments.contract_id', 'contracts.id')
                            ->whereNull('photography_departments.deleted_at');
                    });
                } elseif ($has === '0' || $has === 'false') {
                    $query->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('photography_departments')
                            ->whereColumn('photography_departments.contract_id', 'contracts.id')
                            ->whereNull('photography_departments.deleted_at');
                    });
                }
            }

            if (isset($filters['has_montage'])) {
                $has = (string) $filters['has_montage'];
                if ($has === '1' || $has === 'true') {
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('montage_departments')
                            ->whereColumn('montage_departments.contract_id', 'contracts.id')
                            ->whereNull('montage_departments.deleted_at');
                    });
                } elseif ($has === '0' || $has === 'false') {
                    $query->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('montage_departments')
                            ->whereColumn('montage_departments.contract_id', 'contracts.id')
                            ->whereNull('montage_departments.deleted_at');
                    });
                }
            }

            $query->orderBy('contracts.created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch agency overview: ' . $e->getMessage());
        }
    }


    public function storeContract(array $data): Contract
    {
        DB::beginTransaction();
        try {
            // Set status to pending by default
            $data['status'] = 'pending';
            $data['user_id'] = Auth::id();

            // Create contract
            $contract = Contract::create($data);

            // Calculate and update units totals
            $contract->calculateUnitTotals();
            $contract->save();

            // Reload with relations
            $contract->load(['user', 'info', 'city', 'district']);

            DB::commit();
            return $contract;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create contract: ' . $e->getMessage());
        }
    }

    /**
     * Attach one or more teams to a contract (project_management/admin).
     */
    public function attachTeamsToContract(int $contractId, array $teamIds): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::findOrFail($contractId);

            // Ensure teams exist
            $existingTeamIds = Team::whereIn('id', $teamIds)->pluck('id')->toArray();
            $contract->teams()->syncWithoutDetaching($existingTeamIds);

            DB::commit();
            return $contract->load('teams');
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to attach teams: ' . $e->getMessage());
        }
    }

    /**
     * Detach one or more teams from a contract (project_management/admin).
     */
    public function detachTeamsFromContract(int $contractId, array $teamIds): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::findOrFail($contractId);
            $contract->teams()->detach($teamIds);

            DB::commit();
            return $contract->load('teams');
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to detach teams: ' . $e->getMessage());
        }
    }

    /**
     * Get all teams assigned to a contract.
     */
    public function getContractTeams(int $contractId)
    {
        try {
            $contract = Contract::with('teams')->findOrFail($contractId);
            return $contract->teams;
        } catch (Exception $e) {
            throw new Exception('Failed to fetch contract teams: ' . $e->getMessage());
        }
    }


    public function getContractById(int $id, int $userId = null, bool $forContractInfo = false): Contract
    {
        try {
            // Eager-load related data to prevent N+1 queries
            $contract = Contract::with([
                'user',
                'info',
                'contractUnits',
                'photographyDepartment.processedByUser',
                'boardsDepartment.processedByUser',
                'montageDepartment.processedByUser',
                'city',
                'district',
            ])->findOrFail($id);

            // Authorization check (forContractInfo: only owner, admin, project_management)
            if ($userId) {
                $this->authorizeContractAccess($contract, $userId, $forContractInfo);
            }

            return $contract;
        } catch (Exception $e) {
            throw new Exception('Contract not found or unauthorized: ' . $e->getMessage());
        }
    }


    private function authorizeContractAccess(Contract $contract, int $userId, bool $forContractInfo = false): void
    {
        $authUser = Auth::user();
        $isAdmin = $authUser && (($authUser->type ?? '') === 'admin' || $authUser->hasRole('admin'));
        $isProjectManagementManager = $authUser && $authUser->isProjectManagementManager();
        $isProjectManagement = $authUser && (($authUser->type ?? '') === 'project_management' || $authUser->hasRole('project_management'));
        $isEditor = !$forContractInfo && $authUser && (($authUser->type ?? '') === 'editor' || $authUser->hasRole('editor'));

        if ($forContractInfo) {
            // Store/update contract info: only owner, admin, or project_management manager (is_manager=true)
            if (!$contract->isOwnedBy($userId) && !$isAdmin && !$isProjectManagementManager) {
                throw new Exception('Unauthorized to access this contract.');
            }
        } elseif (!$contract->isOwnedBy($userId) && !$isAdmin && !$isProjectManagement && !$isEditor) {
            throw new Exception('Unauthorized to access this contract.');
        }
    }


    public function updateContract(int $id, array $data, int $userId = null): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['user', 'info', 'city', 'district'])->findOrFail($id);

            // Authorization check
            if ($userId) {
                $this->authorizeContractAccess($contract, $userId);
            }

            // Can only update pending contracts
            if (!$contract->isPending()) {
                throw new Exception('Contract can only be updated when status is pending.');
            }

            // Prevent status update during update operation
            unset($data['status']);

            $contract->update($data);

            // Recalculate units totals if units array changed
            if (isset($data['units']) && is_array($data['units'])) {
                $contract->calculateUnitTotals();
                $contract->save();
            }

            DB::commit();
            return $contract->fresh(['user', 'info', 'city', 'district']);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update contract: ' . $e->getMessage());
        }
    }


    public function deleteContract(int $id, int $userId = null): bool
    {
        DB::beginTransaction();
        try {
            $contract = Contract::findOrFail($id);

            // Authorization check
            if ($userId) {
                $this->authorizeContractAccess($contract, $userId);
            }

            // Check if contract is pending before deletion
            if (!$contract->isPending()) {
                throw new Exception('Only pending contracts can be deleted.');
            }

            $contract->delete();

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete contract: ' . $e->getMessage());
        }
    }


    public function getContractsForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = Contract::with(['photographyDepartment', 'montageDepartment', 'user', 'city', 'district']);

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }

            // Filter by user
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $query->byUser($filters['user_id']);
            }

            // Filter by city (ID)
            if (isset($filters['city_id']) && $filters['city_id'] !== '' && $filters['city_id'] !== null) {
                $query->where('city_id', (int) $filters['city_id']);
            }

            // Filter by district (ID)
            if (isset($filters['district_id']) && $filters['district_id'] !== '' && $filters['district_id'] !== null) {
                $query->where('district_id', (int) $filters['district_id']);
            }

            // Filter by project name (search)
            if (isset($filters['project_name']) && !empty($filters['project_name'])) {
                $query->where('project_name', 'like', '%' . addslashes($filters['project_name']) . '%');
            }

            // Filter by developer name
            if (isset($filters['developer_name']) && !empty($filters['developer_name'])) {
                $query->byDeveloper($filters['developer_name']);
            }

            // Filter by has photography department
            if (isset($filters['has_photography'])) {
                if ($filters['has_photography'] == 1) {
                    $query->whereHas('photographyDepartment');
                } else {
                    $query->whereDoesntHave('photographyDepartment');
                }
            }

            // Filter by has montage department
            if (isset($filters['has_montage'])) {
                if ($filters['has_montage'] == 1) {
                    $query->whereHas('montageDepartment');
                } else {
                    $query->whereDoesntHave('montageDepartment');
                }
            }

            // Sort by latest
            $query->orderBy('created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch contracts: ' . $e->getMessage());
        }
    }


    public function storeContractInfo(int $contractId, array $data, ?Contract $contract = null): ContractInfo
    {
        DB::beginTransaction();
        try {
            // Use provided contract to avoid extra query
            if (!$contract) {
                $contract = Contract::with(['user', 'info'])->findOrFail($contractId);
            }

            // Contract must be approved
            if (!$contract->isApproved()) {
                throw new Exception('Contract must be approved before storing info.');
            }

            // Authorization: owner or admin only
            $this->authorizeContractAccess($contract, Auth::id());

            // Set contract id
            $data['contract_id'] = $contract->id;

            // First party details are fixed by company (cannot be overridden)
            $fixed = [
                'contract_number' => 'ER-' . $contract->id . '-' . time(),
                'first_party_name' => 'شركة راكز العقارية',
                'first_party_cr_number' => '1010650301',
                'first_party_signatory' => 'عبد العزيز خالد عبد العزيز الجلعود',
                'first_party_phone' => '0935027218',
                'first_party_email' => 'info@rakez.sa',
            ];

            // Remove any incoming first-party fields (cannot be overridden)
            foreach (array_keys($fixed) as $field) {
                unset($data[$field]);
            }

            // Merge fixed values with user data
            $data = array_merge($data, $fixed);

            $info = $contract->info()->create($data);

            DB::commit();
            return $info;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to store contract info: ' . $e->getMessage());
        }
    }

    /**
     * Update contract info (only owner, admin, project_management)
     */
    public function updateContractInfo(int $contractId, array $data, int $userId = null): ContractInfo
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['user', 'info'])->findOrFail($contractId);

            // Authorization: only owner, admin, project_management
            if ($userId) {
                $this->authorizeContractAccess($contract, $userId, forContractInfo: true);
            }

            $info = $contract->info;
            if (!$info) {
                // If no info exists, create it instead
                $data['contract_id'] = $contract->id;
                $info = $contract->info()->create($data);
            } else {
                // Remove first-party fields to prevent override
                $protectedFields = ['contract_number', 'first_party_name', 'first_party_cr_number',
                                   'first_party_signatory', 'first_party_phone', 'first_party_email'];
                foreach ($protectedFields as $field) {
                    unset($data[$field]);
                }
                $info->update($data);
            }

            // If contract has info and status is still approved, mark as completed


            DB::commit();
            return $info->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update contract info: ' . $e->getMessage());
        }
    }

    /**
     * Update contract status (admin only, pending to other statuses)
     */
    public function updateContractStatus(int $id, string $status): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['user', 'info'])->findOrFail($id);

            // Validate status
            $validStatuses = ['pending', 'approved', 'rejected', 'completed'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
            }

            // Can only update status from pending
            if (!$contract->isPending()) {
                throw new Exception('Only pending contracts can have their status changed.');
            }

            $contract->update(['status' => $status]);

            DB::commit();
            return $contract->fresh(['user', 'info']);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update contract status: ' . $e->getMessage());
        }
    }

    /**
     * Update contract status by Project Management.
     * Can "mark as ready" (confirm readiness) or reject approved contracts.
     *
     * For 'ready' action: ALL project tracker stages must be complete;
     * contract stays 'approved' and a marketing project is created.
     */
    public function updateContractStatusByProjectManagement(int $id, string $status): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with([
                'user',
                'info',
                'contractUnits',
                'photographyDepartment.processedByUser',
                'boardsDepartment.processedByUser',
                'montageDepartment.processedByUser',
                'city',
                'district',
            ])->findOrFail($id);

            $allowedStatuses = ['ready', 'rejected'];
            if (!in_array($status, $allowedStatuses)) {
                throw new Exception('الحالة يجب أن تكون: جاهز أو مرفوض');
            }

            if (!$contract->isApproved()) {
                throw new Exception('يمكن فقط تحديث العقود الموافق عليها');
            }

            if ($status === 'ready') {
                $readiness = $contract->checkMarketingReadiness();

                if (!$readiness['ready']) {
                    throw new Exception(
                        'لا يمكن تحويل العقد إلى جاهز — المتطلبات التالية غير مكتملة:' . "\n"
                        . implode("\n", array_map(fn($m) => '• ' . $m, $readiness['missing']))
                    );
                }
            }

            if ($status === 'rejected') {
                $contract->update(['status' => $status]);
            }
            // When status === 'ready': leave contract as 'approved', only create marketing project below

            // عند تعيين جاهز، يصبح تلقائياً مشروعاً تسويقياً (الحالة تبقى approved)
            if ($status === 'ready') {
                MarketingProject::firstOrCreate(
                    ['contract_id' => $contract->id],
                    ['status' => 'active']
                );
            }

            DB::commit();
            return $contract->fresh([
                'user',
                'info',
                'contractUnits',
                'photographyDepartment.processedByUser',
                'boardsDepartment.processedByUser',
                'montageDepartment.processedByUser',
                'marketingProject',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
