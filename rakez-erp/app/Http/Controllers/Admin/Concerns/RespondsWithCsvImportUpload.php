<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Responses\ApiResponse;
use App\Models\CsvImport;
use Illuminate\Http\JsonResponse;

trait RespondsWithCsvImportUpload
{
    /**
     * @return array<string, mixed>
     */
    protected function csvImportResultPayload(CsvImport $csvImport): array
    {
        return [
            'import_id' => $csvImport->id,
            'status' => $csvImport->status,
            'total_rows' => $csvImport->total_rows,
            'processed_rows' => $csvImport->processed_rows,
            'successful_rows' => $csvImport->successful_rows,
            'failed_rows' => $csvImport->failed_rows,
            'row_errors' => $csvImport->row_errors,
            'error_message' => $csvImport->error_message,
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

        $message = match ($csvImport->status) {
            CsvImport::STATUS_COMPLETED_WITH_ERRORS => 'تم الاستيراد مع وجود أخطاء في بعض الصفوف',
            default => 'تم استيراد الملف بنجاح',
        };

        return ApiResponse::success($payload, $message);
    }
}
