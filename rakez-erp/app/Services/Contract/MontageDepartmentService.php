<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\MontageDepartment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * قسم المونتاج - Montage Department Service
 */
class MontageDepartmentService
{
    private function getAuthorizedContract(int $contractId): Contract
    {
        $contract = Contract::findOrFail($contractId);

        return $contract;
    }

    public function store(int $contractId, array $data): MontageDepartment
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);

            if ($contract->montageDepartment) {
                throw new Exception('بيانات قسم المونتاج موجودة بالفعل لهذا العقد');
            }

            if (!$contract->info) {
                throw new Exception('يجب أن يكون العقد لديه معلومات عليه قبل إضافة بيانات قسم المونتاج');
            }

            $data['contract_id'] = $contractId;
            $data['processed_by'] = Auth::id();
            $data['processed_at'] = now();

            $montageDepartment = MontageDepartment::create($data);

            DB::commit();

            return $montageDepartment->load('processedByUser');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateByContractId(int $contractId, array $data): MontageDepartment
    {
        DB::beginTransaction();
        try {
            $contract = $this->getAuthorizedContract($contractId);

            if (!$contract->montageDepartment) {
                throw new Exception('بيانات قسم المونتاج غير موجودة لهذا العقد');
            }

            $data['processed_by'] = Auth::id();
            $data['processed_at'] = now();

            $contract->montageDepartment->update($data);

            DB::commit();

            return $contract->montageDepartment->fresh()->load('processedByUser');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getByContractId(int $contractId): ?MontageDepartment
    {
        $contract = $this->getAuthorizedContract($contractId);

        return $contract->montageDepartment?->load('processedByUser');
    }
}

