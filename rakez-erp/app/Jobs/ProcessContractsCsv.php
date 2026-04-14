<?php

namespace App\Jobs;

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
use Illuminate\Validation\Rule;
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

            $service = app(ContractService::class);
            $successful = 0;
            $failed = count($rowErrors);

            if (!empty($validRows)) {
                DB::beginTransaction();
                try {
                    foreach ($validRows as $index => $contractData) {
                        $csvRowNumber = $index + 2;
                        try {
                            $contractData['user_id'] = $this->userId;
                            $contract = $service->storeContract($contractData);
                            $contract->update(['status' => 'approved']);
                            $successful++;
                        } catch (Exception $e) {
                            $rowErrors["row_{$csvRowNumber}"] = ['store' => [$e->getMessage()]];
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

            Log::info("Contract CSV import #{$this->csvImportId} completed: {$successful} ok, {$failed} failed.");

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
        $rules = [
            'developer_name'      => 'required|string|max:255',
            'developer_number'    => 'required|string|max:255',
            'city_id'             => 'required|integer|exists:cities,id',
            'district_id'         => [
                'required', 'integer',
                Rule::exists('districts', 'id')->where(
                    fn ($q) => $q->where('city_id', (int) ($data['city_id'] ?? 0))
                ),
            ],
            'side'                => ['nullable', 'string', Rule::in(['N', 'W', 'E', 'S'])],
            'contract_type'       => 'nullable|string|max:100',
            'project_name'        => 'required|string|max:255',
            'project_image_url'   => 'nullable|string|max:500',
            'developer_requiment' => 'required|string',
            'notes'               => 'nullable|string',
            'commission_percent'  => 'nullable|numeric|min:0',
            'commission_from'     => 'nullable|string|max:255',
            'units'               => 'required|array|min:1',
            'units.*.type'        => 'required|string|max:255',
            'units.*.count'       => 'required|integer|min:1',
            'units.*.price'       => 'required|numeric|min:0',
        ];

        $messages = [
            'developer_name.required'      => 'اسم المطور مطلوب',
            'developer_number.required'    => 'رقم المطور مطلوب',
            'city_id.required'             => 'المدينة مطلوبة',
            'city_id.exists'               => 'المدينة غير موجودة',
            'district_id.required'         => 'الحي مطلوب',
            'district_id.exists'           => 'الحي غير موجود أو لا يتبع المدينة المختارة',
            'project_name.required'        => 'اسم المشروع مطلوب',
            'developer_requiment.required' => 'متطلبات المطور مطلوبة',
            'units.required'               => 'يجب إضافة وحدة واحدة على الأقل',
            'units.min'                    => 'يجب إضافة وحدة واحدة على الأقل',
            'units.*.type.required'        => 'نوع الوحدة مطلوب',
            'units.*.count.required'       => 'عدد الوحدات مطلوب',
            'units.*.count.min'            => 'عدد الوحدات يجب أن يكون أكبر من صفر',
            'units.*.price.required'       => 'سعر الوحدة مطلوب',
            'units.*.price.min'            => 'سعر الوحدة لا يمكن أن يكون سالبًا',
        ];

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
    }
}
