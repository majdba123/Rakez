<?php

namespace App\Jobs;

use App\Http\Requests\Admin\StoreCityRequest;
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
use Exception;

class ProcessCitiesDistrictsCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    protected int $csvImportId;

    private const REQUIRED_COLUMNS = ['city_name', 'city_code'];

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

            foreach ($rows as $index => $row) {
                $csvRowNumber = $index + 2;
                $errors = $this->validateRow($row);

                if (!empty($errors)) {
                    $rowErrors["row_{$csvRowNumber}"] = $errors;
                } else {
                    $validRows[] = $row;
                }
            }

            // Check for duplicate district names within same city in the CSV
            $districtsByCityCode = [];
            foreach ($validRows as $index => $row) {
                if (empty($row['district_name'])) {
                    continue;
                }
                $key = strtolower($row['city_code']);
                $districtName = strtolower(trim($row['district_name']));
                $csvRowNumber = $index + 2;

                if (isset($districtsByCityCode[$key][$districtName])) {
                    $firstRow = $districtsByCityCode[$key][$districtName];
                    $rowErrors["row_{$csvRowNumber}"] = [
                        'district_name' => ["Duplicate district name within city (first seen at row {$firstRow})."],
                    ];
                } else {
                    $districtsByCityCode[$key][$districtName] = $csvRowNumber;
                }
            }

            // Remove rows that had duplicate district errors from validRows
            $validRows = array_filter($validRows, function ($row, $index) use ($rowErrors) {
                return !isset($rowErrors["row_" . ($index + 2)]);
            }, ARRAY_FILTER_USE_BOTH);

            // Phase 2: insert
            $successful = 0;
            $failed = count($rowErrors);

            if (!empty($validRows)) {
                DB::beginTransaction();
                try {
                    foreach ($validRows as $index => $row) {
                        $csvRowNumber = $index + 2;
                        try {
                            // updateOrCreate: re-imports must apply changed city_name for an existing code
                            // (firstOrCreate leaves name unchanged when the row matches by code only).
                            $city = City::updateOrCreate(
                                ['code' => $row['city_code']],
                                ['name' => $row['city_name']]
                            );

                            if (!empty($row['district_name'])) {
                                District::firstOrCreate(
                                    ['city_id' => $city->id, 'name' => $row['district_name']],
                                );
                            }

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

            $csvImport->recordImportOutcome(
                $successful,
                $failed,
                ! empty($rowErrors) ? $rowErrors : null
            );
            Storage::disk('local')->delete($csvImport->file_path);

            Log::info("Cities/Districts CSV import #{$this->csvImportId} completed: {$successful} ok, {$failed} failed.");

        } catch (Exception $e) {
            $csvImport->markFailed($e->getMessage());

            if (Storage::disk('local')->exists($csvImport->file_path)) {
                Storage::disk('local')->delete($csvImport->file_path);
            }

            Log::error("Cities/Districts CSV import #{$this->csvImportId} failed: " . $e->getMessage());
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

        Log::error("ProcessCitiesDistrictsCsv job #{$this->csvImportId} failed", [
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

            if (!array_key_exists('district_name', $mapped)) {
                $mapped['district_name'] = null;
            }

            $rows[] = $mapped;
        }

        fclose($handle);

        if (empty($rows)) {
            throw new Exception('CSV file contains no data rows.');
        }

        return $rows;
    }

    /** Validate a single row. Returns errors array (empty = valid). */
    private function validateRow(array $row): array
    {
        $validator = Validator::make(
            $row,
            StoreCityRequest::citiesDistrictsCsvRowRules(),
            StoreCityRequest::citiesDistrictsCsvRowMessages()
        );

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
    }
}
