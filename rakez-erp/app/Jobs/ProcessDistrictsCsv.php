<?php

namespace App\Jobs;

use App\Models\City;
use App\Models\CsvImport;
use App\Models\District;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Exception;

class ProcessDistrictsCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    protected int $csvImportId;

    private const REQUIRED_COLUMNS = ['city_id', 'name'];

    public function __construct(int $csvImportId)
    {
        $this->csvImportId = $csvImportId;
    }

    public function handle(): void
    {
        $csvImport = CsvImport::findOrFail($this->csvImportId);
        $csvImport->markProcessing();

        try {
            $rows = $this->parseFile($csvImport->file_path);
            $csvImport->update(['total_rows' => count($rows)]);

            // Phase 1: validate all rows
            $rowErrors = [];
            $validRows = [];

            // Track (city_id, name) pairs within CSV for duplicate detection
            $seenPairs = [];

            foreach ($rows as $index => $row) {
                $csvRowNumber = $index + 2;

                $row['city_id'] = isset($row['city_id']) ? (int) $row['city_id'] : null;
                $row['name'] = isset($row['name']) ? trim($row['name']) : null;

                $errors = $this->validateRow($row);

                if (!empty($errors)) {
                    $rowErrors["row_{$csvRowNumber}"] = $errors;
                    continue;
                }

                // Intra-CSV duplicate check
                $pairKey = $row['city_id'] . '|' . strtolower($row['name']);
                if (isset($seenPairs[$pairKey])) {
                    $firstRow = $seenPairs[$pairKey];
                    $rowErrors["row_{$csvRowNumber}"] = [
                        'name' => ["اسم الحي مكرر في نفس المدينة (أول ظهور في صف {$firstRow})."],
                    ];
                    continue;
                }

                $seenPairs[$pairKey] = $csvRowNumber;
                $validRows[$index] = $row;
            }

            // Phase 2: insert
            $successful = 0;
            $failed = count($rowErrors);

            if (!empty($validRows)) {
                DB::beginTransaction();
                try {
                    foreach ($validRows as $index => $row) {
                        $csvRowNumber = $index + 2;
                        try {
                            District::firstOrCreate(
                                ['city_id' => $row['city_id'], 'name' => $row['name']],
                            );
                            $successful++;
                        } catch (Exception $e) {
                            $rowErrors["row_{$csvRowNumber}"] = ['insert' => [$e->getMessage()]];
                            $failed++;
                        }

                        $csvImport->update(['processed_rows' => $successful + $failed]);
                    }

                    if ($successful > 0) {
                        DB::commit();
                    } else {
                        DB::rollBack();
                    }
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            $csvImport->update([
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'processed_rows' => $successful + $failed,
                'row_errors' => !empty($rowErrors) ? $rowErrors : null,
            ]);

            $csvImport->markCompleted();
            Storage::disk('local')->delete($csvImport->file_path);

            Log::info("Districts CSV import #{$this->csvImportId} completed: {$successful} ok, {$failed} failed.");

        } catch (Exception $e) {
            $csvImport->markFailed($e->getMessage());

            if (Storage::disk('local')->exists($csvImport->file_path)) {
                Storage::disk('local')->delete($csvImport->file_path);
            }

            Log::error("Districts CSV import #{$this->csvImportId} failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        $csvImport = CsvImport::find($this->csvImportId);

        if ($csvImport) {
            $csvImport->markFailed($exception->getMessage());

            if (Storage::disk('local')->exists($csvImport->file_path)) {
                Storage::disk('local')->delete($csvImport->file_path);
            }
        }

        Log::error("ProcessDistrictsCsv job #{$this->csvImportId} failed", [
            'error' => $exception->getMessage(),
        ]);
    }

    private function parseFile(string $filePath): array
    {
        $fullPath = Storage::disk('local')->path($filePath);

        if (!file_exists($fullPath)) {
            throw new Exception('CSV file not found on disk.');
        }

        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            throw new Exception('Unable to open CSV file.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('CSV file is empty or has no header row.');
        }

        $header = array_map(fn ($col) => strtolower(trim($col)), $header);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if (!empty($missing)) {
            fclose($handle);
            throw new Exception('CSV is missing required columns: ' . implode(', ', $missing));
        }

        $rows = [];
        $lineNumber = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count($line) !== count($header)) {
                fclose($handle);
                throw new Exception("Row {$lineNumber} has a column count mismatch with the header.");
            }

            $mapped = array_combine($header, array_map('trim', $line));
            foreach ($mapped as $k => $v) {
                if ($v === '') {
                    $mapped[$k] = null;
                }
            }
            $rows[] = $mapped;
        }

        fclose($handle);

        if (empty($rows)) {
            throw new Exception('CSV file contains no data rows.');
        }

        return $rows;
    }

    private function validateRow(array $row): array
    {
        $rules = [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('districts', 'name')->where(
                    fn ($q) => $q->where('city_id', $row['city_id'] ?? 0)
                ),
            ],
        ];

        $messages = [
            'city_id.required' => 'المدينة مطلوبة',
            'city_id.exists'   => 'المدينة غير موجودة',
            'name.required'    => 'اسم الحي مطلوب',
            'name.max'         => 'اسم الحي يجب ألا يتجاوز 255 حرفاً',
            'name.unique'      => 'اسم الحي موجود مسبقاً لهذه المدينة',
        ];

        $validator = Validator::make($row, $rules, $messages);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }
}
