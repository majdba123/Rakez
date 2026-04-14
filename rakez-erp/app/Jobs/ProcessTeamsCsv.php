<?php

namespace App\Jobs;

use App\Http\Requests\Team\StoreTeamRequest;
use App\Models\CsvImport;
use App\Services\Team\TeamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Support\TabularImportReader;
use Exception;

class ProcessTeamsCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    protected int $csvImportId;
    protected int $userId;

    private const REQUIRED_COLUMNS = ['name'];

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

            // Phase 1: validate all rows
            $rowErrors = [];
            $validRows = [];
            $seenNames = [];
            $seenCodes = [];

            foreach ($rows as $index => $row) {
                $csvRowNumber = $index + 2;

                foreach ($row as $k => $v) {
                    if ($v === '') {
                        $row[$k] = null;
                    }
                }

                $errors = $this->validateRow($row, $seenNames, $seenCodes);

                if (!empty($errors)) {
                    $rowErrors["row_{$csvRowNumber}"] = $errors;
                    continue;
                }

                $nameLower = strtolower(trim($row['name']));
                $seenNames[$nameLower] = $csvRowNumber;

                if (!empty($row['code'])) {
                    $seenCodes[strtolower(trim($row['code']))] = $csvRowNumber;
                }

                $validRows[$index] = $row;
            }

            if (! empty($rowErrors)) {
                $csvImport->markImportFailedWithRowErrors(
                    'فشل استيراد الملف: توجد أخطاء تحقق من البيانات ولم يُستورد أي صف. راجع row_errors.',
                    $rowErrors
                );
                Storage::disk('local')->delete($csvImport->file_path);
                Log::info("Teams CSV import #{$this->csvImportId}: validation failed; no rows imported.");

                return;
            }

            // Phase 2: insert (all-or-nothing on store errors)
            $service = app(TeamService::class);
            $successful = 0;

            if (!empty($validRows)) {
                DB::beginTransaction();
                try {
                    $insertErrors = [];
                    foreach ($validRows as $index => $row) {
                        $csvRowNumber = $index + 2;
                        try {
                            $service->storeTeam($row, $this->userId);
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
                        Log::info("Teams CSV import #{$this->csvImportId}: store rolled back; no rows imported.");

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

            Log::info("Teams CSV import #{$this->csvImportId} completed: {$successful} ok.");

        } catch (Exception $e) {
            $csvImport->markFailed($e->getMessage());

            if (Storage::disk('local')->exists($csvImport->file_path)) {
                Storage::disk('local')->delete($csvImport->file_path);
            }

            Log::error("Teams CSV import #{$this->csvImportId} failed: " . $e->getMessage());
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

        Log::error("ProcessTeamsCsv job #{$this->csvImportId} failed", [
            'error' => $exception->getMessage(),
        ]);
    }

    private function parseFile(string $filePath): array
    {
        $fullPath = Storage::disk('local')->path($filePath);

        if (! file_exists($fullPath)) {
            throw new Exception('Import file not found on disk.');
        }

        try {
            $rows = TabularImportReader::parseAssocRows($fullPath);
        } catch (\Throwable $e) {
            throw new Exception($e->getMessage());
        }

        $header = array_keys($rows[0] ?? []);
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if (! empty($missing)) {
            throw new Exception('File is missing required columns: ' . implode(', ', $missing));
        }

        return $rows;
    }

    private function validateRow(array $row, array $seenNames, array $seenCodes): array
    {
        $request = new StoreTeamRequest;
        $validator = Validator::make($row, $request->rules(), $request->messages());

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        // Intra-CSV duplicate checks
        $nameLower = strtolower(trim($row['name']));
        if (isset($seenNames[$nameLower])) {
            return ['name' => ["اسم الفريق مكرر في ملف CSV (أول ظهور في صف {$seenNames[$nameLower]})."]];
        }

        if (!empty($row['code'])) {
            $codeLower = strtolower(trim($row['code']));
            if (isset($seenCodes[$codeLower])) {
                return ['code' => ["رمز الفريق مكرر في ملف CSV (أول ظهور في صف {$seenCodes[$codeLower]})."]];
            }
        }

        return [];
    }
}
