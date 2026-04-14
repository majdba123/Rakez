<?php

namespace App\Jobs;

use App\Http\Requests\Contract\StoreContractRequest;
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

class ProcessContractsCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    protected int $csvImportId;
    protected int $userId;

    private const REQUIRED_COLUMNS = [
        'developer_name', 'developer_number',
        'city_id', 'district_id', 'project_name', 'developer_requiment',
        'unit_type', 'unit_count', 'unit_price',
    ];

    public function __construct(int $csvImportId, int $userId)
    {
        $this->csvImportId = $csvImportId;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $csvImport = CsvImport::findOrFail($this->csvImportId);
        $csvImport->markProcessing();

        try {
            $rows = $this->parseFile($csvImport->file_path);
            $csvImport->update(['total_rows' => count($rows)]);

            $rowErrors = [];
            $validRows = [];

            foreach ($rows as $index => $row) {
                $csvRowNumber = $index + 2;
                $contractData = $this->buildContractPayload($row);
                $errors = $this->validateContract($contractData);

                if (!empty($errors)) {
                    $rowErrors["row_{$csvRowNumber}"] = $errors;
                } else {
                    $validRows[$index] = $contractData;
                }
            }

            if (! empty($rowErrors)) {
                $csvImport->markImportFailedWithRowErrors(
                    'فشل استيراد الملف: توجد أخطاء تحقق من البيانات ولم يُستورد أي صف. راجع row_errors.',
                    $rowErrors
                );
                Storage::disk('local')->delete($csvImport->file_path);
                Log::info("Contract CSV import #{$this->csvImportId}: validation failed; no rows imported.");

                return;
            }

            $service = app(ContractService::class);
            $successful = 0;

            if (!empty($validRows)) {
                DB::beginTransaction();
                try {
                    $insertErrors = [];
                    foreach ($validRows as $index => $contractData) {
                        $csvRowNumber = $index + 2;
                        try {
                            $contractData['user_id'] = $this->userId;
                            $contract = $service->storeContract($contractData);
                            $contract->update(['status' => 'approved']);
                            $successful++;
                        } catch (Exception $e) {
                            $insertErrors["row_{$csvRowNumber}"] = ['store' => [$e->getMessage()]];
                        }

                        $csvImport->update(['processed_rows' => $successful + count($insertErrors)]);
                    }

                    if (! empty($insertErrors)) {
                        DB::rollBack();
                        $csvImport->markImportFailedWithRowErrors(
                            'فشل الاستيراد أثناء الحفظ: لم يُستورد أي صف. راجع row_errors.',
                            $insertErrors
                        );
                        Storage::disk('local')->delete($csvImport->file_path);
                        Log::info("Contract CSV import #{$this->csvImportId}: store rolled back; no rows imported.");

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
                processedRows: $successful,
                skippedRows: 0
            );
            Storage::disk('local')->delete($csvImport->file_path);

            Log::info("Contract CSV import #{$this->csvImportId} completed: {$successful} ok.");

        } catch (Exception $e) {
            $csvImport->markFailed($e->getMessage());

            if (Storage::disk('local')->exists($csvImport->file_path)) {
                Storage::disk('local')->delete($csvImport->file_path);
            }

            Log::error("Contract CSV import #{$this->csvImportId} failed: " . $e->getMessage());
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

        Log::error("ProcessContractsCsv job #{$this->csvImportId} failed", [
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
            $rows[] = array_combine($header, array_map('trim', $line));
        }

        fclose($handle);

        if (empty($rows)) {
            throw new Exception('CSV file contains no data rows.');
        }

        return $rows;
    }

    private function buildContractPayload(array $row): array
    {
        foreach ($row as $k => $v) {
            if ($v === '') {
                $row[$k] = null;
            }
        }

        $contract = [
            'developer_name'     => $row['developer_name'] ?? null,
            'developer_number'   => $row['developer_number'] ?? null,
            'city_id'            => isset($row['city_id']) ? (int) $row['city_id'] : null,
            'district_id'        => isset($row['district_id']) ? (int) $row['district_id'] : null,
            'side'               => isset($row['side']) ? strtoupper($row['side']) : null,
            'contract_type'      => $row['contract_type'] ?? null,
            'project_name'       => $row['project_name'] ?? null,
            'project_image_url'  => $row['project_image_url'] ?? null,
            'developer_requiment' => $row['developer_requiment'] ?? null,
            'notes'              => $row['notes'] ?? null,
            'commission_percent' => isset($row['commission_percent']) ? (float) $row['commission_percent'] : null,
            'commission_from'    => $row['commission_from'] ?? null,
        ];

        $contract['units'] = [
            [
                'type'  => trim((string) ($row['unit_type'] ?? '')),
                'count' => (int) ($row['unit_count'] ?? 0),
                'price' => (float) ($row['unit_price'] ?? 0),
            ],
        ];

        return $contract;
    }

    private function validateContract(array $data): array
    {
        $validator = Validator::make(
            $data,
            StoreContractRequest::contractImportRules($data),
            StoreContractRequest::contractImportMessages()
        );

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }
}
