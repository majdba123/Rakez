<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\ContractInfo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;

class ContractService
{
    /**
     * Get all contracts with filters for users
     */
    public function getContracts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = Contract::with(['photographyDepartment', 'montageDepartment']);

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }

            // Filter by user
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $query->byUser($filters['user_id']);
            }

            // Filter by city
            if (isset($filters['city']) && !empty($filters['city'])) {
                $query->inCity($filters['city']);
            }

            // Filter by district
            if (isset($filters['district']) && !empty($filters['district'])) {
                $query->where('district', $filters['district']);
            }

            // Filter by project name (search)
            if (isset($filters['project_name']) && !empty($filters['project_name'])) {
                $query->where('project_name', 'like', '%' . addslashes($filters['project_name']) . '%');
            }

            // Filter by developer name
            if (isset($filters['developer_name']) && !empty($filters['developer_name'])) {
                $query->byDeveloper($filters['developer_name']);
            }



            // Sort by latest
            $query->orderBy('created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch contracts: ' . $e->getMessage());
        }
    }


    public function storeContract(array $data): Contract
    {
        DB::beginTransaction();
        try {
            // Set status to pending by default
            $data['status'] = 'pending';
            $data['user_id'] = auth()->user()->id;

            // Create contract
            $contract = Contract::create($data);

            // Calculate and update units totals
            $contract->calculateUnitTotals();
            $contract->save();

            // Reload with relations
            $contract->load(['user', 'info']);

            DB::commit();
            return $contract;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create contract: ' . $e->getMessage());
        }
    }


    public function getContractById(int $id, int $userId = null): Contract
    {
        try {
            // Eager-load related data to prevent N+1 queries
            $contract = Contract::with([
                'user',
                'info',
                'secondPartyData.contractUnits',
                'photographyDepartment.processedByUser',
                'boardsDepartment.processedByUser',
                'montageDepartment.processedByUser',
            ])->findOrFail($id);

            // Authorization check
            if ($userId) {
                $this->authorizeContractAccess($contract, $userId);
            }

            return $contract;
        } catch (Exception $e) {
            throw new Exception('Contract not found or unauthorized: ' . $e->getMessage());
        }
    }


    private function authorizeContractAccess(Contract $contract, int $userId): void
    {
        $authUser = auth()->user();
        $isAdmin = $authUser && isset($authUser->type) && $authUser->type === 'admin';
        $isProjectManagement = $authUser && isset($authUser->type) && $authUser->type === 'project_management';
        $isEditor = $authUser && isset($authUser->type) && $authUser->type === 'editor';

        if (!$contract->isOwnedBy($userId) && !$isAdmin && !$isProjectManagement && !$isEditor) {
            throw new Exception('Unauthorized to access this contract.');
        }
    }


    public function updateContract(int $id, array $data, int $userId = null): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['user', 'info'])->findOrFail($id);

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
            return $contract->fresh(['user', 'info']);
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
            $query = Contract::with(['photographyDepartment', 'montageDepartment']);

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }

            // Filter by user
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $query->byUser($filters['user_id']);
            }

            // Filter by city
            if (isset($filters['city']) && !empty($filters['city'])) {
                $query->inCity($filters['city']);
            }

            // Filter by district
            if (isset($filters['district']) && !empty($filters['district'])) {
                $query->where('district', $filters['district']);
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
            $this->authorizeContractAccess($contract, auth()->id());

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
     * Update contract info
     */
    public function updateContractInfo(int $contractId, array $data, int $userId = null): ContractInfo
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['user', 'info'])->findOrFail($contractId);

            // Authorization check
            if ($userId) {
                $this->authorizeContractAccess($contract, $userId);
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
            $validStatuses = ['pending', 'approved', 'rejected', 'completed', 'ready'];
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
     * Update contract status by Project Management
     * Can update approved contracts to 'ready' or 'rejected'
     * For 'ready' status: must have SecondPartyData and CSV units
     */
    public function updateContractStatusByProjectManagement(int $id, string $status): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with([
                'user',
                'info',
                'secondPartyData.contractUnits',
                'photographyDepartment.processedByUser',
                'boardsDepartment.processedByUser',
                'montageDepartment.processedByUser',
            ])->findOrFail($id);

            // Project Management can only set to 'ready' or 'rejected'
            $allowedStatuses = ['ready', 'rejected'];
            if (!in_array($status, $allowedStatuses)) {
                throw new Exception('الحالة يجب أن تكون: ready أو rejected');
            }

            // Can only update approved contracts
            if (!$contract->isApproved()) {
                throw new Exception('يمكن فقط تحديث العقود الموافق عليها');
            }

            // For 'ready' status, must have SecondPartyData and units
            if ($status === 'ready') {
                if (!$contract->secondPartyData) {
                    throw new Exception('يجب إضافة بيانات الطرف الثاني قبل تحويل العقد إلى جاهز');
                }

                if (!$contract->secondPartyData->contractUnits()->exists()) {
                    throw new Exception('يجب رفع ملف الوحدات (CSV) قبل تحويل العقد إلى جاهز');
                }
            }

            $contract->update(['status' => $status]);

            DB::commit();
            return $contract->fresh([
                'user',
                'info',
                'secondPartyData.contractUnits',
                'photographyDepartment.processedByUser',
                'boardsDepartment.processedByUser',
                'montageDepartment.processedByUser',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
