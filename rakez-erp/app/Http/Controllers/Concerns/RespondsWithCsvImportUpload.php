<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Responses\ApiResponse;
use App\Models\CsvImport;
use Illuminate\Http\JsonResponse;
use Throwable;

trait RespondsWithCsvImportUpload
{
    /**
     * When true (default), CSV import jobs run via dispatchSync() in the HTTP request (no queue worker).
     */
    protected function csvImportsUseSyncDispatch(): bool
    {
        return (bool) config('queue.csv_import_dispatch_sync', true);
    }

    /**
     * Run a Process*Csv job synchronously (default) or queued; return final import payload.
     *
     * @param  callable():void  $dispatchSync
     * @param  callable():void  $dispatchAsync
     * @param  bool  $serverErrorViaApiResponse  Admin routes use ApiResponse::serverError for unexpected exceptions
     */
    protected function runCsvImport(
        CsvImport $csvImport,
        callable $dispatchSync,
        callable $dispatchAsync,
        bool $serverErrorViaApiResponse = false
    ): JsonResponse {
        if (! $this->csvImportsUseSyncDispatch()) {
            $dispatchAsync();

            return ApiResponse::success([
                'import_id' => $csvImport->id,
                'status' => $csvImport->status,
            ], 'تم رفع الملف. المعالجة في الطابور — شغّل queue:work أو اضبط CSV_IMPORT_DISPATCH_SYNC=true في .env', 202);
        }

        try {
            $dispatchSync();
        } catch (Throwable $e) {
            $csvImport->refresh();
            if ($csvImport->status !== CsvImport::STATUS_FAILED) {
                if ($serverErrorViaApiResponse) {
                    return ApiResponse::serverError($e->getMessage());
                }

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'import_id' => $csvImport->id,
                ], 500);
            }

            return $this->jsonResponseForCsvImportFinished($csvImport);
        }

        $csvImport->refresh();

        return $this->jsonResponseForCsvImportFinished($csvImport);
    }

    /**
     * @return array<string, mixed>
     */
    protected function csvImportResultPayload(CsvImport $csvImport): array
    {
        $mistakes = $csvImport->mistakesDescription();

        return [
            'import_id' => $csvImport->id,
            'status' => $csvImport->status,
            'total_rows' => $csvImport->total_rows,
            'processed_rows' => $csvImport->processed_rows,
            'successful_rows' => $csvImport->successful_rows,
            'failed_rows' => $csvImport->failed_rows,
            'skipped_rows' => (int) ($csvImport->skipped_rows ?? 0),
            'row_errors' => $csvImport->row_errors,
            'error_message' => $csvImport->error_message,
            'mistakes_description' => $mistakes,
            'failure_reason' => $mistakes,
            'completed_at' => $csvImport->completed_at,
        ];
    }

    /**
     * Response after Process*Csv job finished (sync). Fatal file/parse errors use 422 with error_message and row_errors when applicable.
     */
    protected function jsonResponseForCsvImportFinished(CsvImport $csvImport): JsonResponse
    {
        $payload = $this->csvImportResultPayload($csvImport);

        if ($csvImport->status === CsvImport::STATUS_FAILED) {
            return response()->json([
                'success' => false,
                'message' => $csvImport->error_message ?? 'فشل استيراد الملف',
                'data' => $payload,
            ], 422);
        }

        $successful = (int) $csvImport->successful_rows;
        $failed = (int) $csvImport->failed_rows;
        $skipped = (int) ($csvImport->skipped_rows ?? 0);

        $message = match (true) {
            $csvImport->status === CsvImport::STATUS_COMPLETED_WITH_ERRORS => 'تم الاستيراد مع وجود أخطاء في بعض الصفوف',
            in_array($csvImport->type, [CsvImport::TYPE_CITIES_DISTRICTS, CsvImport::TYPE_DISTRICTS], true)
                && $successful === 0 && $failed === 0 && $skipped > 0
                => 'تمت معالجة الملف دون إضافة بيانات جديدة (جميع الصفوف موجودة مسبقاً).',
            in_array($csvImport->type, [CsvImport::TYPE_CITIES_DISTRICTS, CsvImport::TYPE_DISTRICTS], true)
                && $skipped > 0 && $successful > 0
                => "تم استيراد {$successful} صفاً ببيانات جديدة، و{$skipped} صفاً لم يتغير (موجود مسبقاً).",
            default => match ($csvImport->type) {
                CsvImport::TYPE_CONTRACTS => 'تم استيراد ملف العقود بنجاح',
                CsvImport::TYPE_TEAMS => 'تم استيراد الفرق بنجاح',
                CsvImport::TYPE_EMPLOYEES => 'تم استيراد الموظفين بنجاح',
                CsvImport::TYPE_CONTRACT_INFO => 'تم استيراد معلومات العقد بنجاح',
                CsvImport::TYPE_SECOND_PARTY_DATA => 'تم استيراد بيانات الطرف الثاني بنجاح',
                default => 'تم استيراد الملف بنجاح',
            },
        };

        return ApiResponse::success($payload, $message);
    }
}
