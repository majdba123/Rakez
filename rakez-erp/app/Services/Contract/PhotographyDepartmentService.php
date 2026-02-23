<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\PhotographyDepartment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * قسم التصوير - Photography Department Service
 */
class PhotographyDepartmentService
{
    /**
     * Get contract with authorization check
     */
    private function getAuthorizedContract(int $contractId): Contract
    {
        $contract = Contract::findOrFail($contractId);

        return $contract;
    }

    /**
     * Store photography department data for a contract
     *
     * @param int $contractId
     * @param array $data
     * @return PhotographyDepartment
     * @throws Exception
     */
    public function store(int $contractId, array $data): PhotographyDepartment
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);

            // Check if photography department data already exists
            if ($contract->photographyDepartment) {
                throw new Exception('بيانات قسم التصوير موجودة بالفعل لهذا العقد');
            }

            // Contract must have info before adding photography department data
            if (!$contract->info) {
                throw new Exception('يجب أن يكون العقد لديه معلومات عليه قبل إضافة بيانات قسم التصوير');
            }

            // Set contract_id
            $data['contract_id'] = $contractId;

            // Track who processed this record
            $data['processed_by'] = Auth::id();
            $data['processed_at'] = now();
            $data['status'] = 'pending';

            // Create photography department data
            $photographyDepartment = PhotographyDepartment::create($data);

            DB::commit();

            return $photographyDepartment->load('processedByUser');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update photography department data by contract ID
     *
     * @param int $contractId
     * @param array $data
     * @return PhotographyDepartment
     * @throws Exception
     */
    public function updateByContractId(int $contractId, array $data): PhotographyDepartment
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);

            // Check if photography department data exists
            if (!$contract->photographyDepartment) {
                throw new Exception('بيانات قسم التصوير غير موجودة لهذا العقد');
            }

            // Track who processed this update
            $data['processed_by'] = Auth::id();
            $data['processed_at'] = now();
            // Any update should revert status to pending
            $data['status'] = 'pending';

            // Update only provided fields
            $contract->photographyDepartment->update($data);

            DB::commit();

            return $contract->photographyDepartment->fresh()->load('processedByUser');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get photography department data by contract ID
     *
     * @param int $contractId
     * @return PhotographyDepartment|null
     * @throws Exception
     */
    public function getByContractId(int $contractId): ?PhotographyDepartment
    {
        $contract = $this->getAuthorizedContract($contractId);

        return $contract->photographyDepartment?->load('processedByUser');
    }

    /**
     * Approve photography department (project_management manager only, admin allowed)
     */
    public function approveByContractId(int $contractId): PhotographyDepartment
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);
            $record = $contract->photographyDepartment;

            if (!$record) {
                throw new Exception('بيانات قسم التصوير غير موجودة لهذا العقد');
            }

            $user = Auth::user();
            $isAdmin = $user && $user->type === 'admin';
            $isPmManager = $user && $user->type === 'project_management' && ($user->is_manager ?? false);

            if (!$isAdmin && !$isPmManager) {
                throw new Exception('غير مصرح - هذه الصلاحية متاحة فقط لمدير إدارة المشاريع');
            }

            $record->update([
                'status' => 'approved',
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            DB::commit();
            return $record->fresh()->load('processedByUser');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

