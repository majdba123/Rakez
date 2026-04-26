<?php

namespace App\Jobs;

use App\Models\CsvImport;
use App\Services\registartion\register;
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

class ProcessEmployeesCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    protected int $csvImportId;

    private const REQUIRED_COLUMNS = ['name', 'email', 'password', 'type'];

    private const ALLOWED_COLUMNS = [
        'name', 'email', 'phone', 'password', 'type', 'role',
        'is_manager', 'is_executive_director', 'team', 'identity_number', 'birthday',
        'date_of_works', 'contract_type', 'iban', 'salary', 'marital_status',
    ];

    public function __construct(int $csvImportId)
    {
        $this->csvImportId = $csvImportId;
    }

    public function handle(): void
    {
        $csvImport = CsvImport::findOrFail($this->csvImportId);
        $csvImport->markProcessing();

        try {
            $fullPath = Storage::disk('local')->path($csvImport->file_path);

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
                $filtered = array_intersect_key($mapped, array_flip(self::ALLOWED_COLUMNS));
                $rows[] = $this->castRow($filtered);
            }

            fclose($handle);

            if (empty($rows)) {
                throw new Exception('CSV file contains no data rows.');
            }

            $csvImport->update(['total_rows' => count($rows)]);

            // Phase 1: validate every row before touching the DB
            $rowErrors = [];
            $validRows = [];

            foreach ($rows as $index => $row) {
                $csvRowNumber = $index + 2;
                $rules = $this->rowRules($index, $rows);
                $validator = Validator::make($row, $rules, $this->rowMessages());

                if ($validator->fails()) {
                    $rowErrors["row_{$csvRowNumber}"] = $validator->errors()->toArray();
                } else {
                    $validRows[$index] = $validator->validated();
                }
            }

            if (! empty($rowErrors)) {
                $csvImport->markImportFailedWithRowErrors(
                    'فشل استيراد الملف: توجد أخطاء تحقق من البيانات ولم يُستورد أي صف. راجع row_errors.',
                    $rowErrors
                );
                Storage::disk('local')->delete($csvImport->file_path);
                Log::info("Employee CSV import #{$this->csvImportId}: validation failed; no rows imported.");

                return;
            }

            // Phase 2: insert (all-or-nothing on register errors)
            $service = app(register::class);
            $successful = 0;

            if (!empty($validRows)) {
                DB::beginTransaction();
                try {
                    $insertErrors = [];
                    foreach ($validRows as $index => $data) {
                        $csvRowNumber = $index + 2;
                        try {
                            $service->register($data);
                            $successful++;
                        } catch (Exception $e) {
                            $insertErrors["row_{$csvRowNumber}"] = ['register' => [$e->getMessage()]];
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
                        Log::info("Employee CSV import #{$this->csvImportId}: register rolled back; no rows imported.");

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

            Log::info("Employee CSV import #{$this->csvImportId} completed: {$successful} ok.");

        } catch (Exception $e) {
            $csvImport->markFailed($e->getMessage());

            if (Storage::disk('local')->exists($csvImport->file_path)) {
                Storage::disk('local')->delete($csvImport->file_path);
            }

            Log::error("Employee CSV import #{$this->csvImportId} failed: " . $e->getMessage());

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

        Log::error("ProcessEmployeesCsv job #{$this->csvImportId} failed", [
            'error' => $exception->getMessage(),
        ]);
    }

    private function rowRules(int $currentIndex, array $allRows): array
    {
        $otherEmails = collect($allRows)->except($currentIndex)->pluck('email')->filter()->values()->toArray();
        $otherPhones = collect($allRows)->except($currentIndex)->pluck('phone')->filter()->values()->toArray();
        $otherIds    = collect($allRows)->except($currentIndex)->pluck('identity_number')->filter()->values()->toArray();

        return [
            'name'            => 'required|string|max:255',
            'email'           => ['required', 'string', 'email', 'max:255', 'unique:users', Rule::notIn($otherEmails)],
            'phone'           => ['nullable', 'string', 'max:20', 'unique:users', Rule::notIn($otherPhones)],
            'password'        => 'required|string|min:8',
            'type'            => ['required', 'integer', Rule::in(config('user_types.valid_ids', range(1, 13)))],
            'role'            => 'nullable|string|exists:roles,name',
            'is_manager'      => 'nullable|boolean',
            'is_executive_director' => 'nullable|boolean',
            'team'            => 'nullable|integer|exists:teams,id',
            'identity_number' => ['nullable', 'string', 'max:100', 'unique:users,identity_number', Rule::notIn($otherIds)],
            'birthday'        => 'nullable|date',
            'date_of_works'   => 'nullable|date',
            'contract_type'   => 'nullable|string|max:100',
            'iban'            => 'nullable|string|max:34',
            'salary'          => 'nullable|numeric|min:0',
            'marital_status'  => 'nullable|string|in:single,married,divorced,widowed',
        ];
    }

    private function rowMessages(): array
    {
        return [
            'email.unique'           => 'Email already exists in the database.',
            'email.not_in'           => 'Duplicate email within the CSV file.',
            'phone.unique'           => 'Phone already exists in the database.',
            'phone.not_in'           => 'Duplicate phone within the CSV file.',
            'identity_number.unique' => 'Identity number already exists in the database.',
            'identity_number.not_in' => 'Duplicate identity number within the CSV file.',
            'type.in'               => 'User type must be one of the accepted values.',
            'role.exists'            => 'The selected role does not exist.',
            'marital_status.in'      => 'Must be one of: single, married, divorced, widowed.',
        ];
    }

    private function castRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value === '') {
                $row[$key] = null;
            }
        }

        if (isset($row['type'])) {
            $row['type'] = (int) $row['type'];
        }
        if (isset($row['is_manager'])) {
            $row['is_manager'] = filter_var($row['is_manager'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if (isset($row['is_executive_director'])) {
            $row['is_executive_director'] = filter_var($row['is_executive_director'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if (isset($row['salary'])) {
            $row['salary'] = (float) $row['salary'];
        }
        if (isset($row['team'])) {
            $row['team'] = (int) $row['team'];
        }

        return $row;
    }
}
