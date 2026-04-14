<?php

namespace App\Jobs;

use App\Http\Requests\Admin\StoreDistrictRequest;
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
                $row['_line'] = $csvRowNumber;
                $validRows[] = $row;
            }

            if (! empty($rowErrors)) {
                $csvImport->markImportFailedWithRowErrors(
                    'فشل استيراد الملف: توجد أخطاء تحقق من البيانات ولم يُستورد أي صف. راجع row_errors.',
                    $rowErrors
                );
                Storage::disk('local')->delete($csvImport->file_path);
                Log::info("Districts CSV import #{$this->csvImportId}: validation failed; no rows imported.");

                return;
            }

            // Phase 2: insert only (existing districts unchanged; all-or-nothing on errors)
            $successful = 0;
            $skipped = 0;

            if (!empty($validRows)) {
                DB::beginTransaction();
                try {
                    $insertErrors = [];
                    foreach ($validRows as $row) {
                        $csvRowNumber = $row['_line'];
                        $rowForDb = $row;
                        unset($rowForDb['_line']);

                        try {
                            $exists = District::query()
                                ->where('city_id', $rowForDb['city_id'])
                                ->where('name', $rowForDb['name'])
                                ->exists();

                            if ($exists) {
                                $skipped++;
                            } else {
                                District::create([
                                    'city_id' => $rowForDb['city_id'],
                                    'name' => $rowForDb['name'],
                                ]);
                                $successful++;
                            }
                        } catch (Exception $e) {
                            $insertErrors["row_{$csvRowNumber}"] = ['insert' => [$e->getMessage()]];
                        }

                        $csvImport->update(['processed_rows' => $successful + $skipped + count($insertErrors)]);
                    }

                    if (! empty($insertErrors)) {
                        DB::rollBack();
                        $csvImport->markImportFailedWithRowErrors(
                            'فشل الاستيراد أثناء الحفظ: لم يُستورد أي صف. راجع row_errors.',
                            $insertErrors
                        );
                        Storage::disk('local')->delete($csvImport->file_path);
                        Log::info("Districts CSV import #{$this->csvImportId}: insert rolled back; no rows imported.");

                        return;
                    }

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            $csvImport->recordImportOutcome(
                successful: $successful,
                failed: 0,
                rowErrors: null,
                processedRows: $successful + $skipped,
                skippedRows: $skipped
            );
            Storage::disk('local')->delete($csvImport->file_path);

            Log::info("Districts CSV import #{$this->csvImportId} completed: {$successful} new, {$skipped} unchanged.");

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
        $cityId = (int) ($row['city_id'] ?? 0);
        $request = new StoreDistrictRequest;
        $validator = Validator::make(
            $row,
            StoreDistrictRequest::rulesForCsvRow($cityId),
            $request->messages()
        );

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }
}
