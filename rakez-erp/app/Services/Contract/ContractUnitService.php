<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Services\Sales\DispatchUnitSearchAlertMatching;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContractUnitService
{
    public function uploadCsvByContractId(int $contractId, UploadedFile $file): array
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['secondPartyData', 'info'])->findOrFail($contractId);

            if (! $contract->info) {
                throw new Exception('يجب أن يكون العقد لديه معلومات قبل إضافة الوحدات');
            }

            if (ContractUnit::where('contract_id', $contractId)->exists()) {
                ContractUnit::where('contract_id', $contractId)->forceDelete();
            }

            $secondPartyData = $contract->secondPartyData;
            if ($secondPartyData) {
                $secondPartyData->update([
                    'processed_by' => Auth::id(),
                    'processed_at' => now(),
                ]);
            }

            $createdUnitIds = $this->processCsvFile($file, $contractId);

            DB::commit();

            $this->dispatchSearchAlertMatching($createdUnitIds);

            return [
                'message' => 'تم رفع ومعالجة الملف بنجاح',
                'status' => 'completed',
                'contract_id' => $contractId,
                'units_created' => count($createdUnitIds),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process CSV file directly (no queue)
     */
    private function processCsvFile(UploadedFile $file, int $contractId): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new Exception('فشل في فتح ملف CSV');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            throw new Exception('ملف CSV فارغ أو تالف');
        }

        $header = array_map(function ($col) {
            return strtolower(trim($col));
        }, $header);

        $columnMap = [
            'unit_type' => ['unit_type', 'type', 'نوع_الوحدة', 'نوع'],
            'unit_number' => ['unit_number', 'number', 'رقم_الوحدة', 'رقم'],
            'status' => ['status', 'الحالة'],
            'price' => ['price', 'unit_price', 'السعر', 'سعر_الوحدة'],
            'area' => ['area', 'size', 'المساحة'],
            'floor' => ['floor', 'الطابق'],
            'bedrooms' => ['bedrooms', 'غرف', 'عدد_الغرف'],
            'bathrooms' => ['bathrooms', 'حمامات', 'عدد_الحمامات'],
            'private_area_m2' => ['private_area_m2', 'private_area', 'المساحة_الخاصة', 'الشرفة'],
            'street_width' => ['street_width', 'street width', 'width_street', 'width street', 'عرض_الشارع', 'عرض الشارع'],
            'facade' => ['view', 'facade', 'الواجهة', 'الاتجاه'],
            'description' => ['description', 'desc', 'الوصف', 'ملاحظات'],
            'description_en' => ['description_en', 'description en', 'الوصف_انجليزي'],
            'description_ar' => ['description_ar', 'description ar', 'الوصف_عربي'],
            'diagrames' => ['diagrames', 'diagrams', 'المخططات'],
        ];

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

        $createdUnitIds = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $unitData = [
                'contract_id' => $contractId,
                'status' => 'pending',
            ];

            foreach ($columnIndices as $field => $index) {
                if (isset($row[$index]) && $row[$index] !== '') {
                    $value = trim($row[$index]);

                    switch ($field) {
                        case 'price':
                        case 'area':
                        case 'private_area_m2':
                        case 'street_width':
                            $unitData[$field] = (float) $value;
                            break;
                        case 'floor':
                        case 'bedrooms':
                        case 'bathrooms':
                            $unitData[$field] = (int) $value;
                            break;
                        default:
                            $unitData[$field] = $value;
                    }
                }
            }

            if (isset($unitData['description'])) {
                if (! isset($unitData['description_en'])) {
                    $unitData['description_en'] = $unitData['description'];
                }
                unset($unitData['description']);
            }

            $createdUnitIds[] = ContractUnit::create($unitData)->id;
        }

        fclose($handle);

        return $createdUnitIds;
    }

    public function getUnitsByContractId(int $contractId, int $perPage = 15): LengthAwarePaginator
    {
        Contract::findOrFail($contractId);

        return ContractUnit::where('contract_id', $contractId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Check if current user is authorized to modify units
     */
    private function authorizeUnitModification(Contract $contract): void
    {
        $currentUser = Auth::user();
        $currentUserId = Auth::id();

        if ($currentUser && $currentUser->can('units.edit')) {
            return;
        }

        $secondPartyData = $contract->secondPartyData;
        if ($secondPartyData && $secondPartyData->processed_by === $currentUserId) {
            return;
        }

        throw new Exception('غير مصرح لك بتعديل وحدات هذا العقد. فقط الموظف الذي قام بمعالجة العقد يمكنه التعديل');
    }

    public function addUnit(int $contractId, array $data): ContractUnit
    {
        DB::beginTransaction();
        try {
            $contract = Contract::with(['secondPartyData', 'info'])->findOrFail($contractId);

            if (! $contract->info) {
                throw new Exception('يجب أن يكون العقد لديه معلومات قبل إضافة الوحدات');
            }

            $this->authorizeUnitModification($contract);

            $data['contract_id'] = $contractId;

            if (! isset($data['status'])) {
                $data['status'] = 'pending';
            }

            $unit = ContractUnit::create($data);

            DB::commit();

            $this->dispatchSearchAlertMatching([$unit->id]);

            return $unit;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateUnit(int $unitId, array $data): ContractUnit
    {
        DB::beginTransaction();
        try {
            $unit = ContractUnit::with('contract')->findOrFail($unitId);

            $this->authorizeUnitModification($unit->contract);

            $allowedFields = [
                'unit_type',
                'unit_number',
                'status',
                'price',
                'area',
                'floor',
                'bedrooms',
                'bathrooms',
                'private_area_m2',
                'street_width',
                'facade',
                'description_en',
                'description_ar',
                'diagrames',
            ];

            $filteredData = array_intersect_key($data, array_flip($allowedFields));

            $unit->update($filteredData);

            DB::commit();

            $this->dispatchSearchAlertMatching([$unit->id]);

            return $unit->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteUnit(int $unitId): bool
    {
        DB::beginTransaction();
        try {
            $unit = ContractUnit::with('contract')->findOrFail($unitId);

            $this->authorizeUnitModification($unit->contract);

            $unit->forceDelete();

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function dispatchSearchAlertMatching(array $unitIds): void
    {
        try {
            app(DispatchUnitSearchAlertMatching::class)->dispatchManySafely($unitIds, [
                'source' => 'contract_unit_service',
            ]);
        } catch (Throwable $e) {
            Log::warning('Unit search alert matching dispatch helper failed', [
                'source' => 'contract_unit_service',
                'unit_count' => count(array_unique(array_filter($unitIds))),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
