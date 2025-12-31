<?php

namespace App\Jobs;

use App\Models\ContractUnit;
use App\Models\SecondPartyData;
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

    protected int $secondPartyDataId;
    protected string $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(int $secondPartyDataId, string $filePath)
    {
        $this->secondPartyDataId = $secondPartyDataId;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::beginTransaction();
        try {
            $secondPartyData = SecondPartyData::findOrFail($this->secondPartyDataId);

            // Read CSV file
            $fullPath = Storage::disk('local')->path($this->filePath);

            if (!file_exists($fullPath)) {
                throw new Exception('ملف CSV غير موجود');
            }

            $file = fopen($fullPath, 'r');

            if ($file === false) {
                throw new Exception('فشل في فتح ملف CSV');
            }

            // Read header row
            $header = fgetcsv($file);

            if ($header === false) {
                fclose($file);
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
                'count' => ['count', 'quantity', 'العدد', 'الكمية'],
                'status' => ['status', 'الحالة'],
                'price' => ['price', 'unit_price', 'السعر', 'سعر_الوحدة'],
                'total_price' => ['total_price', 'total', 'السعر_الإجمالي', 'الإجمالي'],
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

            $rowCount = 0;
            $errors = [];

            // Process each row
            while (($row = fgetcsv($file)) !== false) {
                $rowCount++;

                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $unitData = [
                        'second_party_data_id' => $this->secondPartyDataId,
                        'status' => 'pending',
                    ];

                    // Map CSV data to unit fields
                    foreach ($columnIndices as $field => $index) {
                        if (isset($row[$index]) && $row[$index] !== '') {
                            $value = trim($row[$index]);

                            // Type casting
                            switch ($field) {
                                case 'count':
                                    $unitData[$field] = (int) $value;
                                    break;
                                case 'price':
                                case 'total_price':
                                case 'area':
                                    $unitData[$field] = (float) $value;
                                    break;
                                default:
                                    $unitData[$field] = $value;
                            }
                        }
                    }

                    // Create unit
                    ContractUnit::create($unitData);

                } catch (Exception $e) {
                    $errors[] = "صف {$rowCount}: " . $e->getMessage();
                    Log::warning("CSV Row {$rowCount} error: " . $e->getMessage());
                }
            }

            fclose($file);

            // Delete temp file after processing
            Storage::disk('local')->delete($this->filePath);

            DB::commit();

            Log::info("CSV processed successfully for SecondPartyData ID: {$this->secondPartyDataId}. Rows: {$rowCount}");

            if (!empty($errors)) {
                Log::warning("CSV processing completed with errors", ['errors' => $errors]);
            }

        } catch (Exception $e) {
            DB::rollBack();

            // Clean up file on failure
            if (Storage::disk('local')->exists($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }

            Log::error("CSV processing failed for SecondPartyData ID: {$this->secondPartyDataId}. Error: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        // Clean up file on failure
        if (Storage::disk('local')->exists($this->filePath)) {
            Storage::disk('local')->delete($this->filePath);
        }

        Log::error("ProcessContractUnitsCsv job failed for SecondPartyData ID: {$this->secondPartyDataId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

