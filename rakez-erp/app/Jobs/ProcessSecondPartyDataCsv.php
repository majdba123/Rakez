<?php

namespace App\Jobs;

use App\Models\CsvImport;
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

            // Merge all CSV rows into one (first non-null value per field wins)
            $merged = [];
            foreach ($rows as $row) {
                foreach ($row as $key => $value) {
                    $value = trim($value);
                    if ($value !== '' && !isset($merged[$key])) {
                        $merged[$key] = $value;
                    }
                }
            }

            // Set nulls for empty fields
            foreach (self::ALLOWED_COLUMNS as $col) {
                if (!isset($merged[$col])) {
                    $merged[$col] = null;
                }
            }

            $rowErrors = [];
            $errors = $this->validateRow($merged);
            if (!empty($errors)) {
                $rowErrors['row_2'] = $errors;
            }

            $successful = 0;
            $failed = count($rowErrors);

            if (empty($rowErrors)) {
                DB::beginTransaction();
                try {
                    $service = app(SecondPartyDataService::class);
                    $service->store($this->contractId, $merged);

                    $successful = 1;
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $rowErrors['contract'] = ['store' => [$e->getMessage()]];
                    $failed = 1;
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

            Log::info("SecondPartyData CSV import #{$this->csvImportId} completed: {$successful} ok, {$failed} failed.");

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
        $rules = [
            'real_estate_papers_url'    => 'nullable|url|max:500',
            'plans_equipment_docs_url'  => 'nullable|url|max:500',
            'project_logo_url'          => 'nullable|url|max:500',
            'prices_units_url'          => 'nullable|url|max:500',
            'marketing_license_url'     => 'nullable|url|max:500',
            'advertiser_section_url'    => 'nullable|string|max:50|regex:/^[0-9]+$/',
        ];

        $messages = [
            'real_estate_papers_url.url'       => 'رابط اوراق العقار يجب أن يكون رابط صحيح',
            'plans_equipment_docs_url.url'     => 'رابط مستندات المخططات والتجهيزات يجب أن يكون رابط صحيح',
            'project_logo_url.url'             => 'رابط شعار المشروع يجب أن يكون رابط صحيح',
            'prices_units_url.url'             => 'رابط الاسعار والوحدات يجب أن يكون رابط صحيح',
            'marketing_license_url.url'        => 'رابط رخصة التسويق يجب أن يكون رابط صحيح',
            'advertiser_section_url.regex'     => 'رقم قسم المعلن يجب أن يكون أرقام فقط',
            'advertiser_section_url.max'       => 'رقم قسم المعلن يجب أن لا يتجاوز 50 رقم',
        ];

        $validator = Validator::make($row, $rules, $messages);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }
}
