<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class ContractUnitService
{

    public function uploadCsvByContractId(int $contractId, UploadedFile $file): array
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['secondPartyData.contractUnits', 'info'])->findOrFail($contractId);

            // Contract must have info before adding units
            if (!$contract->info) {
                throw new Exception('يجب أن يكون العقد لديه معلومات قبل إضافة الوحدات');
            }

            // Contract must have SecondPartyData
            $secondPartyData = $contract->secondPartyData;
            if (!$secondPartyData) {
                throw new Exception('يجب إضافة بيانات الطرف الثاني قبل رفع ملف الوحدات');
            }

            // Delete old units if exist (replace with new)
            if ($secondPartyData->contractUnits()->exists()) {
                $secondPartyData->contractUnits()->forceDelete();
            }

            // Update processed_by info
            $secondPartyData->update([
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            // Process CSV directly
            $unitsCreated = $this->processCsvFile($file, $secondPartyData->id);

            DB::commit();

            return [
                'message' => 'تم رفع ومعالجة الملف بنجاح',
                'status' => 'completed',
                'contract_id' => $contractId,
                'second_party_data_id' => $secondPartyData->id,
                'units_created' => $unitsCreated,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process CSV file directly (no queue)
     */
    private function processCsvFile(UploadedFile $file, int $secondPartyDataId): int
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new Exception('فشل في فتح ملف CSV');
        }

        // Read header row
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            throw new Exception('ملف CSV فارغ أو تالف');
        }

        // Normalize header (trim and lowercase)
        $header = array_map(function ($col) {
            return strtolower(trim($col));
        }, $header);

        // Map CSV columns to database fields
        $columnMap = [
            'unit_type' => ['unit_type', 'type', 'نوع_الوحدة', 'نوع'],
            'unit_number' => ['unit_number', 'number', 'رقم_الوحدة', 'رقم'],
            'status' => ['status', 'الحالة'],
            'price' => ['price', 'unit_price', 'السعر', 'سعر_الوحدة'],
            'area' => ['area', 'size', 'المساحة'],
            'description' => ['description', 'desc', 'الوصف', 'ملاحظات'],
        ];

        // Find column indices
        $columnIndices = [];
        foreach ($columnMap as $field => $possibleNames) {
            foreach ($possibleNames as $name) {
                $index = array_search($name, $header);
                if ($index !== false) {
                    $columnIndices[$field] = $index;
                    break;
                }
            }
        }

        $unitsCreated = 0;

        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $unitData = [
                'second_party_data_id' => $secondPartyDataId,
                'status' => 'pending',
            ];

            // Map CSV data to unit fields
            foreach ($columnIndices as $field => $index) {
                if (isset($row[$index]) && $row[$index] !== '') {
                    $value = trim($row[$index]);

                    // Type casting
                    switch ($field) {
                        case 'price':
                            $unitData[$field] = (float) $value;
                            break;
                        default:
                            $unitData[$field] = $value;
                    }
                }
            }

            // Create unit
            ContractUnit::create($unitData);
            $unitsCreated++;
        }

        fclose($handle);

        return $unitsCreated;
    }


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
     * Check if current user is authorized to modify units
     * Allows:
     * - The employee who processed the contract
     * - Users with 'units.edit' permission (e.g., PM staff, admins)
     */
    private function authorizeUnitModification(SecondPartyData $secondPartyData): void
    {
        $currentUser = Auth::user();
        $currentUserId = Auth::id();

        // Allow if user has units.edit permission (PM staff, admin)
        if ($currentUser && $currentUser->can('units.edit')) {
            return;
        }

        // Allow if user is the one who processed this contract
        if ($secondPartyData->processed_by !== $currentUserId) {
            throw new Exception('غير مصرح لك بتعديل وحدات هذا العقد. فقط الموظف الذي قام بمعالجة العقد يمكنه التعديل');
        }
    }

    /**
     * Add a single unit to a contract
     * Only the employee who processed the contract can add
     */
    public function addUnit(int $contractId, array $data): ContractUnit
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['secondPartyData', 'info'])->findOrFail($contractId);

            // Contract must have info
            if (!$contract->info) {
                throw new Exception('يجب أن يكون العقد لديه معلومات قبل إضافة الوحدات');
            }

            // Contract must have SecondPartyData
            $secondPartyData = $contract->secondPartyData;
            if (!$secondPartyData) {
                throw new Exception('يجب إضافة بيانات الطرف الثاني قبل إضافة الوحدات');
            }

            // Check authorization - only the employee who processed can add
            $this->authorizeUnitModification($secondPartyData);

            // Set second_party_data_id
            $data['second_party_data_id'] = $secondPartyData->id;

            // Set default status if not provided
            if (!isset($data['status'])) {
                $data['status'] = 'pending';
            }

            // Create unit
            $unit = ContractUnit::create($data);

            DB::commit();

            return $unit;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a unit by ID
     * Only the employee who processed the contract can update
     */
    public function updateUnit(int $unitId, array $data): ContractUnit
    {
        DB::beginTransaction();
        try {
            $unit = ContractUnit::with('secondPartyData')->findOrFail($unitId);

            // Check authorization - only the employee who processed can update
            $this->authorizeUnitModification($unit->secondPartyData);

            // Filter only allowed fields
            $allowedFields = [
                'unit_type',
                'unit_number',
                'status',
                'price',
                'area',
                'description',
            ];

            $filteredData = array_intersect_key($data, array_flip($allowedFields));

            $unit->update($filteredData);

            DB::commit();

            return $unit->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a unit by ID
     * Only the employee who processed the contract can delete
     */
    public function deleteUnit(int $unitId): bool
    {
        DB::beginTransaction();
        try {
            $unit = ContractUnit::with('secondPartyData')->findOrFail($unitId);

            // Check authorization
            $this->authorizeUnitModification($unit->secondPartyData);

            $unit->forceDelete();

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

