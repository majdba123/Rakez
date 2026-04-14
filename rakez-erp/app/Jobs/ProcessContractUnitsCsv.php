<?php

namespace App\Jobs;

use App\Models\ContractUnit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessContractUnitsCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    protected int $contractId;
    protected string $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(int $contractId, string $filePath)
    {
        $this->contractId = $contractId;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::beginTransaction();
        try {
            $fullPath = Storage::disk('local')->path($this->filePath);

            if (!file_exists($fullPath)) {
                throw new Exception('ملف CSV غير موجود');
            }

            $file = fopen($fullPath, 'r');

            if ($file === false) {
                throw new Exception('فشل في فتح ملف CSV');
            }

            $header = fgetcsv($file);

            if ($header === false) {
                fclose($file);
                throw new Exception('ملف CSV فارغ أو تالف');
            }

            $header = array_map(function ($col) {
                return strtolower(trim($col));
            }, $header);

            // Keys must match ContractUnit::$fillable (use facade for orientation; DB column is facade).
            $columnMap = [
                'unit_type' => ['unit_type', 'type', 'نوع_الوحدة', 'نوع'],
                'unit_number' => ['unit_number', 'number', 'رقم_الوحدة', 'رقم'],
                'count' => ['count', 'quantity', 'العدد', 'الكمية'],
                'status' => ['status', 'الحالة'],
                'price' => ['price', 'unit_price', 'السعر', 'سعر_الوحدة'],
                'total_price' => ['total_price', 'total', 'السعر_الإجمالي', 'الإجمالي'],
                'area' => ['area', 'size', 'المساحة'],
                'floor' => ['floor', 'الطابق'],
                'bedrooms' => ['bedrooms', 'غرف', 'عدد_الغرف'],
                'bathrooms' => ['bathrooms', 'حمامات', 'عدد_الحمامات'],
                'private_area_m2' => ['private_area_m2', 'private_area', 'المساحة_الخاصة', 'الشرفة'],
                'facade' => ['facade', 'view', 'الواجهة', 'الاتجاه'],
                'description_en' => ['description_en', 'description', 'desc', 'الوصف', 'ملاحظات', 'الوصف_انجليزي'],
                'description_ar' => ['description_ar', 'الوصف_عربي'],
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

            $rowCount = 0;
            $errors = [];

            while (($row = fgetcsv($file)) !== false) {
                $rowCount++;

                try {
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $unitData = [
                        'contract_id' => $this->contractId,
                        'status' => 'pending',
                    ];

                    foreach ($columnIndices as $field => $index) {
                        if (isset($row[$index]) && $row[$index] !== '') {
                            $value = trim($row[$index]);

                            switch ($field) {
                                case 'count':
                                case 'floor':
                                case 'bedrooms':
                                case 'bathrooms':
                                    $unitData[$field] = (int) $value;
                                    break;
                                case 'price':
                                case 'total_price':
                                case 'private_area_m2':
                                    $unitData[$field] = (float) $value;
                                    break;
                                case 'area':
                                    // Stored as string in schema; keep numeric string for consistency
                                    $unitData[$field] = $value;
                                    break;
                                default:
                                    $unitData[$field] = $value;
                            }
                        }
                    }

                    if (empty($unitData['unit_type']) || empty($unitData['unit_number']) || ! array_key_exists('price', $unitData)) {
                        throw new Exception('يتطلب الأعمدة unit_type و unit_number و price');
                    }

                    ContractUnit::create($unitData);

                } catch (Exception $e) {
                    $errors[] = "صف {$rowCount}: " . $e->getMessage();
                    Log::warning("CSV Row {$rowCount} error: " . $e->getMessage());
                }
            }

            fclose($file);

            Storage::disk('local')->delete($this->filePath);

            DB::commit();

            Log::info("CSV processed successfully for contract ID: {$this->contractId}. Rows: {$rowCount}");

            if (!empty($errors)) {
                Log::warning("CSV processing completed with errors", ['errors' => $errors]);
            }

        } catch (Exception $e) {
            DB::rollBack();

            if (Storage::disk('local')->exists($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }

            Log::error("CSV processing failed for contract ID: {$this->contractId}. Error: " . $e->getMessage());

            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        if (Storage::disk('local')->exists($this->filePath)) {
            Storage::disk('local')->delete($this->filePath);
        }

        Log::error("ProcessContractUnitsCsv job failed for contract ID: {$this->contractId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
