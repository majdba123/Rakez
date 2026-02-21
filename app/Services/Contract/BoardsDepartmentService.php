<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\BoardsDepartment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * قسم اللوحات - Boards Department Service
 */
class BoardsDepartmentService
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
     * Store boards department data for a contract
     *
     * @param int $contractId
     * @param array $data
     * @return BoardsDepartment
     * @throws Exception
     */
    public function store(int $contractId, array $data): BoardsDepartment
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);

            // Check if boards department data already exists
            if ($contract->boardsDepartment) {
                throw new Exception('بيانات قسم اللوحات موجودة بالفعل لهذا العقد');
            }

            // Contract must have info before adding boards department data
            if (!$contract->info) {
                throw new Exception('يجب أن يكون العقد لديه معلومات عليه قبل إضافة بيانات قسم اللوحات');
            }

            // Set contract_id
            $data['contract_id'] = $contractId;

            // Track who processed this record
            $data['processed_by'] = Auth::id();
            $data['processed_at'] = now();

            // Create boards department data
            $boardsDepartment = BoardsDepartment::create($data);

            DB::commit();

            return $boardsDepartment->load('processedByUser');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function updateByContractId(int $contractId, array $data): BoardsDepartment
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);

            // Check if boards department data exists
            if (!$contract->boardsDepartment) {
                throw new Exception('بيانات قسم اللوحات غير موجودة لهذا العقد');
            }

            // Track who processed this update
            $data['processed_by'] = Auth::id();
            $data['processed_at'] = now();

            // Update only provided fields
            $contract->boardsDepartment->update($data);

            DB::commit();

            return $contract->boardsDepartment->fresh()->load('processedByUser');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get boards department data by contract ID
     *
     * @param int $contractId
     * @return BoardsDepartment|null
     * @throws Exception
     */
    public function getByContractId(int $contractId): ?BoardsDepartment
    {
        $contract = $this->getAuthorizedContract($contractId);

        return $contract->boardsDepartment?->load('processedByUser');
    }
}

