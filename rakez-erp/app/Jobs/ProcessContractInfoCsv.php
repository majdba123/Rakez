<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\CsvImport;
use App\Services\Contract\ContractService;
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

class ProcessContractInfoCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    protected int $csvImportId;
    protected int $userId;
    protected int $contractId;

    private const ALLOWED_COLUMNS = [
        'gregorian_date', 'hijri_date', 'contract_city', 'location_url',
        'agreement_duration_days', 'agency_number', 'agency_date',
        'avg_property_value', 'release_date',
        'second_party_name', 'second_party_address', 'second_party_cr_number',
        'second_party_signatory', 'second_party_id_number', 'second_party_role',
        'second_party_phone', 'second_party_email',
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

            // Validate each CSV row independently; reasons appear under row_N in csv_imports.row_errors
            $rowErrors = [];
            foreach ($rows as $index => $row) {
                $row = $this->castRow($row);
                $csvRowNumber = $index + 2;
                $errors = $this->validateRow($row);
                if (! empty($errors)) {
                    $rowErrors["row_{$csvRowNumber}"] = $errors;
                }
            }

            if (! empty($rowErrors)) {
                $csvImport->recordImportOutcome(0, count($rowErrors), $rowErrors, count($rows));
                Storage::disk('local')->delete($csvImport->file_path);
                Log::info("ContractInfo CSV import #{$this->csvImportId}: validation failed on ".count($rowErrors).' row(s).');

                return;
            }

            // Merge all rows (all valid): first non-null per field wins
            $merged = [];
            foreach ($rows as $row) {
                $row = $this->castRow($row);
                foreach ($row as $key => $value) {
                    if ($value !== null && ! isset($merged[$key])) {
                        $merged[$key] = $value;
                    }
                }
            }

            $successful = 0;
            $failed = 0;
            $finalRowErrors = [];

            DB::beginTransaction();
            try {
                $contract = Contract::with(['user', 'info'])->findOrFail($this->contractId);
                $service = app(ContractService::class);

                $service->storeContractInfo($this->contractId, $merged, $contract);
                $contract->update(['status' => 'completed']);

                $successful = 1;
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $finalRowErrors['import'] = ['store' => [$e->getMessage()]];
                $failed = 1;
            }

            $csvImport->recordImportOutcome($successful, $failed, $finalRowErrors ?: null, count($rows));
            Storage::disk('local')->delete($csvImport->file_path);

            Log::info("ContractInfo CSV import #{$this->csvImportId} completed: {$successful} ok, {$failed} failed.");

        } catch (Exception $e) {
            $csvImport->markFailed($e->getMessage());

            if (Storage::disk('local')->exists($csvImport->file_path)) {
                Storage::disk('local')->delete($csvImport->file_path);
            }

            Log::error("ContractInfo CSV import #{$this->csvImportId} failed: " . $e->getMessage());
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

        Log::error("ProcessContractInfoCsv job #{$this->csvImportId} failed", [
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
        $hasValidColumn = !empty(array_intersect($header, array_keys($allowed)));
        if (!$hasValidColumn) {
            fclose($handle);
            throw new Exception('CSV has no recognized contract info columns.');
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
            $filtered = array_intersect_key($mapped, array_flip(self::ALLOWED_COLUMNS));
            $rows[] = $filtered;
        }

        fclose($handle);

        if (empty($rows)) {
            throw new Exception('CSV file contains no data rows.');
        }

        return $rows;
    }

    private function castRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value === '') {
                $row[$key] = null;
            }
        }

        if (isset($row['agreement_duration_days'])) {
            $row['agreement_duration_days'] = (int) $row['agreement_duration_days'];
        }
        if (isset($row['avg_property_value'])) {
            $row['avg_property_value'] = (float) $row['avg_property_value'];
        }

        return $row;
    }

    private function validateRow(array $row): array
    {
        $rules = [
            'gregorian_date'           => 'nullable|date',
            'hijri_date'               => 'nullable|string|max:50',
            'contract_city'            => 'nullable|string|max:255',
            'location_url'             => 'nullable|string|url|max:500',
            'agreement_duration_days'  => 'nullable|integer|min:0',
            'agency_number'            => 'nullable|string|max:255',
            'agency_date'              => 'nullable|date',
            'avg_property_value'       => 'nullable|numeric|min:0',
            'release_date'             => 'nullable|date',
            'second_party_name'        => 'nullable|string|max:255',
            'second_party_address'     => 'nullable|string',
            'second_party_cr_number'   => 'nullable|string|max:255',
            'second_party_signatory'   => 'nullable|string|max:255',
            'second_party_id_number'   => 'nullable|string|max:255',
            'second_party_role'        => 'nullable|string|max:255',
            'second_party_phone'       => 'nullable|string|max:255',
            'second_party_email'       => 'nullable|string|email|max:255',
        ];

        $messages = [
            'gregorian_date.date'  => 'يجب أن يكون تاريخ العقد الميلادي صالحاً',
            'hijri_date.string'    => 'يجب أن يكون تاريخ العقد الهجري نصاً',
            'location_url.url'     => 'رابط الموقع يجب أن يكون عنوان URL صالحاً',
            'second_party_email.email' => 'البريد الإلكتروني للطرف الثاني غير صالح',
        ];

        $validator = Validator::make($row, $rules, $messages);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }
}
