<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondsWithCsvImportUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDistrictRequest;
use App\Http\Requests\Admin\UpdateDistrictRequest;
use App\Http\Requests\Admin\ImportDistrictsCsv;
use App\Http\Responses\ApiResponse;
use App\Jobs\ProcessDistrictsCsv;
use App\Models\CsvImport;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class DistrictController extends Controller
{
    use RespondsWithCsvImportUpload;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'city_code' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', 'in:name,id,city_id,created_at,updated_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = ApiResponse::getPerPage($request);

        $query = District::query()->with('city');

        if ($request->filled('city_id')) {
            $query->where('city_id', (int) $request->input('city_id'));
        }

        if ($cc = trim((string) $request->input('city_code', ''))) {
            $query->whereHas('city', function ($q) use ($cc) {
                $q->where('code', 'like', '%' . $cc . '%');
            });
        }

        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%')
                    ->orWhereHas('city', function ($cq) use ($q) {
                        $cq->where('name', 'like', '%' . $q . '%')
                            ->orWhere('code', 'like', '%' . $q . '%');
                    });
            });
        }

        if ($name = trim((string) $request->input('name', ''))) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->input('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->input('created_to'));
        }

        $sortField = match ($request->input('sort', 'name')) {
            'id' => 'id',
            'city_id' => 'city_id',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            default => 'name',
        };
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $direction);

        $districts = $query->paginate($perPage);

        $districts->getCollection()->transform(fn (District $d) => $this->toArray($d));

        return ApiResponse::paginated($districts, 'تم جلب قائمة الأحياء بنجاح');
    }

    public function store(StoreDistrictRequest $request): JsonResponse
    {
        $district = District::create($request->validated());
        $district->load('city');

        return ApiResponse::created($this->toArray($district), 'تم إنشاء الحي بنجاح');
    }

    public function show(int $id): JsonResponse
    {
        $district = District::with('city')->find($id);

        if (!$district) {
            return ApiResponse::notFound('الحي غير موجود');
        }

        return ApiResponse::success($this->toArray($district), 'تم جلب الحي بنجاح');
    }

    public function update(UpdateDistrictRequest $request, int $id): JsonResponse
    {
        $district = District::find($id);

        if (!$district) {
            return ApiResponse::notFound('الحي غير موجود');
        }

        $district->update($request->validated());
        $district->load('city');

        return ApiResponse::success($this->toArray($district), 'تم تحديث الحي بنجاح');
    }

    public function destroy(int $id): JsonResponse
    {
        $district = District::find($id);

        if (!$district) {
            return ApiResponse::notFound('الحي غير موجود');
        }

        $district->delete();

        return ApiResponse::success(null, 'تم حذف الحي بنجاح');
    }

    /**
     * Upload CSV for bulk district import. Dispatches a queue job.
     */
    public function import_csv(ImportDistrictsCsv $request): JsonResponse
    {
        $file = $request->file('file');

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return ApiResponse::error('Unable to read the CSV file.', 422);
        }

        $header = fgetcsv($handle);
        fclose($handle);

        if (!$header) {
            return ApiResponse::error('CSV file is empty or has no header row.', 422);
        }

        $header = array_map(fn ($col) => strtolower(trim($col)), $header);
        $missing = array_diff(['city_id', 'name'], $header);

        if (!empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'CSV is missing required columns.',
                'missing_columns' => array_values($missing),
            ], 422);
        }

        $storedPath = $file->store('csv-imports', 'local');

        $csvImport = CsvImport::create([
            'type' => CsvImport::TYPE_DISTRICTS,
            'uploaded_by' => Auth::id(),
            'file_path' => $storedPath,
            'status' => CsvImport::STATUS_PENDING,
        ]);

        try {
            ProcessDistrictsCsv::dispatchSync($csvImport->id);
        } catch (Throwable $e) {
            $csvImport->refresh();
            if ($csvImport->status !== CsvImport::STATUS_FAILED) {
                return ApiResponse::serverError($e->getMessage());
            }

            return $this->jsonResponseForCsvImportFinished($csvImport);
        }

        $csvImport->refresh();

        return $this->jsonResponseForCsvImportFinished($csvImport);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(District $district): array
    {
        $city = $district->city;

        return [
            'id' => $district->id,
            'city_id' => $district->city_id,
            'name' => $district->name,
            'city' => $city ? [
                'id' => $city->id,
                'name' => $city->name,
                'code' => $city->code,
            ] : null,
            'created_at' => $district->created_at?->toIso8601String(),
            'updated_at' => $district->updated_at?->toIso8601String(),
        ];
    }
}
