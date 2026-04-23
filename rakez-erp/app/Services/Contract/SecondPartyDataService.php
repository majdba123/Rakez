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

            $data = SecondPartyData::normalizeCompletionFieldsInPayload($data);

            // Create second party data
            $secondPartyData = SecondPartyData::create($data);
            $secondPartyData->syncIsCompleteSecondOnContract();

            DB::commit();

            return $secondPartyData->load(['contract.contractUnits', 'processedByUser']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * CSV import: create row when missing; if it already exists, only apply non-empty fields from the file (keeps existing URLs/data for columns not provided or left blank).
     */
    public function mergeFromCsvImport(int $contractId, array $merged): SecondPartyData
    {
        $contract = $this->getAuthorizedContract($contractId);

        if (! $contract->info) {
            throw new Exception('يجب أن يكون العقد لديه معلومات عليه قبل إضافة بيانات الطرف الثاني');
        }

        if (! $contract->secondPartyData) {
            return $this->store($contractId, $merged);
        }

        DB::beginTransaction();
        try {
            $patch = [];
            foreach (SecondPartyData::fieldNamesRequiredForContractCompletion() as $field) {
                if (! array_key_exists($field, $merged)) {
                    continue;
                }
                $v = $merged[$field];
                if ($v !== null && (! is_string($v) || trim((string) $v) !== '')) {
                    $patch[$field] = $v;
                }
            }

            $patch['processed_by'] = Auth::id();
            $patch['processed_at'] = now();
            $patch = SecondPartyData::normalizeCompletionFieldsInPayload($patch);

            $contract->secondPartyData->update($patch);
            $contract->secondPartyData->refresh();
            $contract->secondPartyData->syncIsCompleteSecondOnContract();

            DB::commit();

            return $contract->secondPartyData->fresh()->load(['contract.contractUnits', 'processedByUser']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

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

            $data = SecondPartyData::normalizeCompletionFieldsInPayload($data);

            // Update only provided fields (null clears a column; flag syncs to false if any field empty)
            $contract->secondPartyData->update($data);
            $contract->secondPartyData->refresh();
            $contract->secondPartyData->syncIsCompleteSecondOnContract();

            DB::commit();

            return $contract->secondPartyData->fresh()->load(['contract.contractUnits', 'processedByUser']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function getByContractId(int $contractId): ?SecondPartyData
    {
        $contract = $this->getAuthorizedContract($contractId);

        return $contract->secondPartyData?->load(['contract.contractUnits', 'processedByUser']);
    }




}

