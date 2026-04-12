<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CsvImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CsvImportController extends Controller
{
    /**
     * Paginated list of all CSV imports (newest first). Filter by type, status, uploader.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', Rule::in(CsvImport::allTypes())],
            'status' => ['nullable', 'string', Rule::in([
                CsvImport::STATUS_PENDING,
                CsvImport::STATUS_PROCESSING,
                CsvImport::STATUS_COMPLETED,
                CsvImport::STATUS_FAILED,
                CsvImport::STATUS_COMPLETED_WITH_ERRORS,
            ])],
            'uploaded_by' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = ApiResponse::getPerPage($request);

        $query = CsvImport::query()
            ->with(['uploader:id,name,email'])
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('uploaded_by')) {
            $query->where('uploaded_by', (int) $request->input('uploaded_by'));
        }

        $paginated = $query->paginate($perPage);
        $paginated->getCollection()->transform(fn (CsvImport $import) => $this->toListResource($import));

        return ApiResponse::paginated($paginated, 'تم جلب سجل استيرادات CSV بنجاح');
    }

    /**
     * Single import with full row_errors (for admin troubleshooting).
     */
    public function show(int $id): JsonResponse
    {
        $import = CsvImport::query()
            ->with(['uploader:id,name,email'])
            ->find($id);

        if (!$import) {
            return ApiResponse::notFound('سجل الاستيراد غير موجود');
        }

        return ApiResponse::success($this->toDetailResource($import), 'تم جلب سجل الاستيراد بنجاح');
    }

    /**
     * Allowed import types and labels (for filters / UI).
     */
    public function types(): JsonResponse
    {
        return ApiResponse::success([
            'types' => CsvImport::typesCatalog(),
        ], 'تم جلب أنواع الاستيراد بنجاح');
    }

    /**
     * @return array<string, mixed>
     */
    private function toListResource(CsvImport $import): array
    {
        return [
            'id' => $import->id,
            'type' => $import->type,
            'type_label' => CsvImport::labelForType($import->type),
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->failed_rows,
            'error_message' => $import->error_message,
            'completed_at' => $import->completed_at,
            'created_at' => $import->created_at,
            'updated_at' => $import->updated_at,
            'file_path' => $import->file_path,
            'uploaded_by' => $import->uploader ? [
                'id' => $import->uploader->id,
                'name' => $import->uploader->name,
                'email' => $import->uploader->email,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetailResource(CsvImport $import): array
    {
        return $this->toListResource($import) + [
            'row_errors' => $import->row_errors,
        ];
    }
}
