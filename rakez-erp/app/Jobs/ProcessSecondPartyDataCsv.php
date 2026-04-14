<?php

namespace App\Jobs;

use App\Http\Requests\Contract\StoreSecondPartyDataRequest;
use App\Models\CsvImport;
use App\Models\SecondPartyData;
use App\Services\Contract\SecondPartyDataService;
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

class ProcessSecondPartyDataCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    protected int $csvImportId;
    protected int $userId;
    protected int $contractId;

    private const ALLOWED_COLUMNS = [
        'real_estate_papers_url',
        'plans_equipment_docs_url',
        'project_logo_url',
        'prices_units_url',
        'marketing_license_url',
        'advertiser_section_url',
    ];

    public function __construct(int $csvImportId, int $userId, int $contractId)
    {
        $this->csvImportId = $csvImportId;
        $this->userId = $userId;
        $this->contractId = $contractId;
    }

    public function handle(): void
    {
        $csvImport = CsvImport::findOrFail($this->csvImportId);
        $csvImport->markProcessing();

        try {
            $rows = $this->parseFile($csvImport->file_path);
            $csvImport->update(['total_rows' => count($rows)]);

            // Per-row validation (each line must pass URL / field rules)
            $rowErrors = [];
            foreach ($rows as $index => $row) {
                $csvRowNumber = $index + 2;
                $normalized = [];
                foreach (self::ALLOWED_COLUMNS as $col) {
                    $val = $row[$col] ?? '';
                    $normalized[$col] = trim((string) $val);
                }
                $payload = [];
                foreach (self::ALLOWED_COLUMNS as $col) {
                    $payload[$col] = $normalized[$col] !== '' ? $normalized[$col] : null;
                }

                $errors = $this->validateRow($payload);
                if (! empty($errors)) {
                    $rowErrors["row_{$csvRowNumber}"] = $errors;
                }
            }

            if (! empty($rowErrors)) {
                $csvImport->markImportFailedWithRowErrors(
                    'فشل استيراد الملف: توجد أخطاء تحقق من البيانات ولم يُحفظ شيء. راجع row_errors.',
                    $rowErrors
                );
                Storage::disk('local')->delete($csvImport->file_path);
                Log::info("SecondPartyData CSV import #{$this->csvImportId}: validation failed on ".count($rowErrors).' row(s).');

                return;
            }

            // Merge: first non-empty per column
            $merged = [];
            foreach ($rows as $row) {
                foreach ($row as $key => $value) {
                    $value = trim((string) $value);
                    if ($value !== '' && ! isset($merged[$key])) {
                        $merged[$key] = $value;
                    }
                }
            }
            foreach (self::ALLOWED_COLUMNS as $col) {
                if (! isset($merged[$col])) {
                    $merged[$col] = null;
                }
            }

            $successful = 0;

            DB::beginTransaction();
            try {
                $service = app(SecondPartyDataService::class);
                $service->mergeFromCsvImport($this->contractId, $merged);

                $successful = 1;
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $csvImport->markImportFailedWithRowErrors(
                    'فشل الاستيراد أثناء الحفظ: لم يُحفظ أي بيانات. راجع row_errors.',
                    ['import' => ['store' => [$e->getMessage()]]]
                );
                Storage::disk('local')->delete($csvImport->file_path);
                Log::info("SecondPartyData CSV import #{$this->csvImportId}: store failed; nothing saved.");

                return;
            }

            $csvImport->recordImportOutcome(
                successful: $successful,
                failed: 0,
                rowErrors: null,
                processedRows: count($rows),
                skippedRows: 0
            );
            Storage::disk('local')->delete($csvImport->file_path);

            Log::info("SecondPartyData CSV import #{$this->csvImportId} completed: {$successful} ok.");

        } catch (Exception $e) {
            $csvImport->markFailed($e->getMessage());

            if (Storage::disk('local')->exists($csvImport->file_path)) {
                Storage::disk('local')->delete($csvImport->file_path);
            }

            Log::error("SecondPartyData CSV import #{$this->csvImportId} failed: " . $e->getMessage());
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

        Log::error("ProcessSecondPartyDataCsv job #{$this->csvImportId} failed", [
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

        $allowed = array_flip(self::ALLOWED_COLUMNS);
        if (empty(array_intersect($header, array_keys($allowed)))) {
            fclose($handle);
            throw new Exception('CSV has no recognized second party data columns.');
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
            $rows[] = array_intersect_key($mapped, $allowed);
        }

        fclose($handle);

        if (empty($rows)) {
            throw new Exception('CSV file contains no data rows.');
        }

        return $rows;
    }

    private function validateRow(array $row): array
    {
        $request = new StoreSecondPartyDataRequest;
        $validator = Validator::make($row, $request->rules(), $request->messages());

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }
}
