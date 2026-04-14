<?php

namespace App\Jobs;

use App\Http\Requests\Contract\StoreContractRequest;
use App\Models\City;
use App\Models\CsvImport;
use App\Models\District;
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
        'project_name', 'developer_requiment',
        'units_json',
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
                    'لم يُحفظ أي عقد: فشل التحقق من صحة بيانات الملف قبل الحفظ، ولم يُجرَ أي تعديل على قاعدة البيانات. '
                    .'رقم الصف في row_errors يطابق رقم السطر في ملف CSV (الصف 2 = أول سطر بيانات بعد العناوين). '
                    .'إن ظهرت أخطاء على المدينة أو الحي: تأكد أن city_id و district_id تطابقان سجلاتاً موجودة فعلياً في النظام، أو استورد ملف المدن والأحياء أولاً ثم استخدم أعمدة city_code و district_name في ملف العقود.',
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
                            unset($contractData['_units_json_error'], $contractData['_location_error']);
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
                            'تعذّر إكمال الحفظ: اجتازت البيانات التحقق لكن حدث خطأ أثناء التسجيل، فتم التراجع عن العملية ولم يُحفظ أي عقد. '
                            .'تفاصيل كل صف موجودة في row_errors. إن لم تكن الرسالة واضحة، راجع قيود النظام أو سجلات الخادم.',
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

        $hasIds = in_array('city_id', $header, true) && in_array('district_id', $header, true);
        $hasCode = in_array('city_code', $header, true) && in_array('district_name', $header, true);
        if (! $hasIds && ! $hasCode) {
            fclose($handle);
            throw new Exception('CSV must include either (city_id and district_id) or (city_code and district_name) columns.');
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

        $cityCode = isset($row['city_code']) && $row['city_code'] !== null ? trim((string) $row['city_code']) : '';
        $districtName = isset($row['district_name']) && $row['district_name'] !== null ? trim((string) $row['district_name']) : '';
        $hasCodePair = $cityCode !== '' && $districtName !== '';

        $cityId = null;
        $districtId = null;
        $locationError = null;

        if ($hasCodePair) {
            $city = City::query()->where('code', $cityCode)->first();
            $cityId = $city?->id;
            if ($city === null) {
                $locationError = "لم يُعثر على مدينة بالرمز «{$cityCode}». استورد المدن أولاً أو راجع city_code.";
            } else {
                $districtId = District::query()
                    ->where('city_id', $city->id)
                    ->where('name', $districtName)
                    ->value('id');
                if ($districtId === null) {
                    $locationError = "لم يُعثر على حي «{$districtName}» للمدينة ذات الرمز «{$cityCode}». راجع district_name أو استورد الأحياء.";
                }
            }
        } else {
            if (isset($row['city_id']) && $row['city_id'] !== null && $row['city_id'] !== '') {
                $cityId = (int) $row['city_id'];
            }
            if (isset($row['district_id']) && $row['district_id'] !== null && $row['district_id'] !== '') {
                $districtId = (int) $row['district_id'];
            }
        }

        $contract = [
            'developer_name'     => $row['developer_name'] ?? null,
            'developer_number'   => $row['developer_number'] ?? null,
            'city_id'            => $cityId,
            'district_id'        => $districtId,
            'side'               => isset($row['side']) ? strtoupper($row['side']) : null,
            'contract_type'      => $row['contract_type'] ?? null,
            'project_name'       => $row['project_name'] ?? null,
            'project_image_url'  => $row['project_image_url'] ?? null,
            'developer_requiment' => $row['developer_requiment'] ?? null,
            'notes'              => $row['notes'] ?? null,
            'commission_percent' => isset($row['commission_percent']) ? (float) $row['commission_percent'] : null,
            'commission_from'    => $row['commission_from'] ?? null,
        ];

        $unitsJsonRaw = $row['units_json'] ?? null;
        $unitsJson = ($unitsJsonRaw !== null && $unitsJsonRaw !== '')
            ? trim((string) $unitsJsonRaw)
            : '';

        if ($unitsJson === '') {
            $contract['units'] = [];
            $contract['_units_json_error'] = 'حقل units_json مطلوب: مصفوفة JSON مثل [{"type":"apartment","count":10,"price":500000}]';
        } else {
            $decoded = json_decode($unitsJson, true);
            if (! is_array($decoded)) {
                $contract['units'] = [];
                $contract['_units_json_error'] = 'حقل units_json يجب أن يكون مصفوفة JSON صالحة، مثل: [{"type":"apartment","count":10,"price":500000}]';
            } else {
                $normalized = [];
                foreach ($decoded as $u) {
                    if (! is_array($u)) {
                        continue;
                    }
                    $normalized[] = [
                        'type' => trim((string) ($u['type'] ?? '')),
                        'count' => (int) ($u['count'] ?? 0),
                        'price' => (float) ($u['price'] ?? 0),
                    ];
                }
                $contract['units'] = $normalized;
            }
        }

        if ($locationError !== null) {
            $contract['_location_error'] = $locationError;
        }

        return $contract;
    }

    private function validateContract(array $data): array
    {
        $locationError = $data['_location_error'] ?? null;
        $jsonError = $data['_units_json_error'] ?? null;
        $dataForRules = $data;
        unset($dataForRules['_units_json_error'], $dataForRules['_location_error']);

        $validator = Validator::make(
            $dataForRules,
            StoreContractRequest::contractImportRules($dataForRules),
            StoreContractRequest::contractImportMessages()
        );

        $errors = $validator->fails() ? $validator->errors()->toArray() : [];

        if ($locationError !== null && $locationError !== '') {
            unset($errors['city_id'], $errors['district_id']);
            $errors['location'] = array_merge($errors['location'] ?? [], [$locationError]);
        }

        if ($jsonError !== null && $jsonError !== '') {
            $errors['units_json'] = array_values(array_merge(
                $errors['units_json'] ?? [],
                is_array($jsonError) ? $jsonError : [$jsonError]
            ));
        }

        return $errors;
    }
}
