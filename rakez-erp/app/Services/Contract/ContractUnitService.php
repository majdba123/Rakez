<?php

namespace App\Services\Contract;

use App\Jobs\ProcessContractUnitsCsv;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class ContractUnitService
{
    /**
     * Upload CSV file and dispatch job to process it
     * Only one CSV upload is allowed per SecondPartyData
     */
    public function uploadCsv(int $secondPartyDataId, UploadedFile $file): array
    {
        DB::beginTransaction();
        try {
            $secondPartyData = SecondPartyData::with('contractUnits')->findOrFail($secondPartyDataId);

            // Check if units already exist (only one CSV upload allowed)
            if ($secondPartyData->contractUnits()->exists()) {
                throw new Exception('تم رفع ملف CSV مسبقاً لهذا العقد. لا يمكن رفع ملف آخر');
            }

            // Store file temporarily
            $fileName = 'csv_uploads/' . uniqid('units_') . '_' . time() . '.csv';
            Storage::disk('local')->put($fileName, file_get_contents($file->getRealPath()));

            // Dispatch job to queue
            ProcessContractUnitsCsv::dispatch($secondPartyDataId, $fileName);

            DB::commit();

            return [
                'message' => 'تم استلام الملف وسيتم معالجته في الخلفية',
                'status' => 'processing',
                'second_party_data_id' => $secondPartyDataId,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get all units for a SecondPartyData with pagination
     */
    public function getUnitsBySecondPartyDataId(int $secondPartyDataId, int $perPage = 15): LengthAwarePaginator
    {
        $secondPartyData = SecondPartyData::findOrFail($secondPartyDataId);

        return ContractUnit::where('second_party_data_id', $secondPartyDataId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get all units for a contract by contract ID
     */
    public function getUnitsByContractId(int $contractId, int $perPage = 15): LengthAwarePaginator
    {
        $contract = Contract::with('secondPartyData')->findOrFail($contractId);

        if (!$contract->secondPartyData) {
            throw new Exception('بيانات الطرف الثاني غير موجودة لهذا العقد');
        }

        return ContractUnit::where('second_party_data_id', $contract->secondPartyData->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get single unit by ID
     */
    public function getUnitById(int $unitId): ContractUnit
    {
        return ContractUnit::with('secondPartyData.contract')->findOrFail($unitId);
    }

    /**
     * Update a unit by ID
     */
    public function updateUnit(int $unitId, array $data): ContractUnit
    {
        DB::beginTransaction();
        try {
            $unit = ContractUnit::findOrFail($unitId);

            // Filter only fillable fields
            $allowedFields = [
                'unit_type',
                'unit_number',
                'count',
                'status',
                'price',
                'total_price',
                'area',
                'description',
            ];

            $filteredData = array_intersect_key($data, array_flip($allowedFields));

            $unit->update($filteredData);

            DB::commit();

            return $unit->fresh()->load('secondPartyData');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    /**
     * Get units statistics for a SecondPartyData
     */
    public function getUnitsStats(int $secondPartyDataId): array
    {
        $units = ContractUnit::where('second_party_data_id', $secondPartyDataId);

        return [
            'total_count' => $units->count(),
            'total_units' => (int) $units->sum('count'),
            'total_value' => (float) $units->sum('total_price'),
            'total_area' => (float) $units->sum('area'),
            'by_status' => ContractUnit::where('second_party_data_id', $secondPartyDataId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }
}

