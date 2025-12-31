<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\SecondPartyData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class SecondPartyDataService
{
    /**
     * Get contract with authorization check
     */
    private function getAuthorizedContract(int $contractId): Contract
    {
        $contract = Contract::findOrFail($contractId);


        return $contract;
    }

    public function store(int $contractId, array $data): SecondPartyData
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);

            // Check if second party data already exists
            if ($contract->secondPartyData) {
                throw new Exception('بيانات الطرف الثاني موجودة بالفعل لهذا العقد');
            }

            // Contract must have info before adding second party data
            if (!$contract->info) {
                throw new Exception('يجب أن يكون العقد لديه معلومات عليه قبل إضافة بيانات الطرف الثاني');
            }

            // Set contract_id
            $data['contract_id'] = $contractId;

            // Track who processed this record
            $data['processed_by'] = Auth::id();
            $data['processed_at'] = now();

            // Create second party data
            $secondPartyData = SecondPartyData::create($data);

            DB::commit();

            return $secondPartyData->load(['contractUnits', 'processedByUser']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update second party data by contract ID
     *
     * @param int $contractId
     * @param array $data
     * @return SecondPartyData
     * @throws Exception
     */
    public function updateByContractId(int $contractId, array $data): SecondPartyData
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);

            // Check if second party data exists
            if (!$contract->secondPartyData) {
                throw new Exception('بيانات الطرف الثاني غير موجودة لهذا العقد');
            }

            // Track who processed this update
            $data['processed_by'] = Auth::id();
            $data['processed_at'] = now();

            // Update only provided fields
            $contract->secondPartyData->update($data);

            DB::commit();

            return $contract->secondPartyData->fresh()->load(['contractUnits', 'processedByUser']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get second party data by contract ID
     *
     * @param int $contractId
     * @return SecondPartyData|null
     * @throws Exception
     */
    public function getByContractId(int $contractId): ?SecondPartyData
    {
        $contract = $this->getAuthorizedContract($contractId);

        return $contract->secondPartyData?->load(['contractUnits', 'processedByUser']);
    }




}

