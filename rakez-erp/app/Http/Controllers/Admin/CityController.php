<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondsWithCsvImportUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCityRequest;
use App\Http\Requests\Admin\UpdateCityRequest;
use App\Http\Requests\Admin\ImportCitiesDistrictsCsv;
use App\Http\Responses\ApiResponse;
use App\Jobs\ProcessCitiesDistrictsCsv;
use App\Models\City;
use App\Models\CsvImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CityController extends Controller
{
    use RespondsWithCsvImportUpload;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', 'in:name,code,id,created_at,updated_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'has_districts' => ['nullable'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = ApiResponse::getPerPage($request);

        $query = City::query();

        if ($request->has('has_districts') && $request->input('has_districts') !== null && $request->input('has_districts') !== '') {
            $v = $request->input('has_districts');
            if ($v === '1' || $v === 1 || $v === true) {
                $query->whereHas('districts');
            } elseif ($v === '0' || $v === 0 || $v === false) {
                $query->whereDoesntHave('districts');
            }
        }

        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%')
                    ->orWhere('code', 'like', '%' . $q . '%');
            });
        }

        if ($name = trim((string) $request->input('name', ''))) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        if ($code = trim((string) $request->input('code', ''))) {
            $query->where('code', 'like', '%' . $code . '%');
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->input('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->input('created_to'));
        }

        $sortField = match ($request->input('sort', 'name')) {
            'code' => 'code',
            'id' => 'id',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            default => 'name',
        };
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $direction);

        $cities = $query->paginate($perPage);

        return ApiResponse::paginated($cities, 'تم جلب قائمة المدن بنجاح');
    }

    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = City::create($request->validated());

        return ApiResponse::created(
            [
                'id' => $city->id,
                'name' => $city->name,
                'code' => $city->code,
                'created_at' => $city->created_at?->toIso8601String(),
                'updated_at' => $city->updated_at?->toIso8601String(),
            ],
            'تم إنشاء المدينة بنجاح'
        );
    }

    public function show(int $id): JsonResponse
    {
        $city = City::find($id);

        if (!$city) {
            return ApiResponse::notFound('المدينة غير موجودة');
        }

        return ApiResponse::success([
            'id' => $city->id,
            'name' => $city->name,
            'code' => $city->code,
            'created_at' => $city->created_at?->toIso8601String(),
            'updated_at' => $city->updated_at?->toIso8601String(),
        ], 'تم جلب المدينة بنجاح');
    }

    public function update(UpdateCityRequest $request, int $id): JsonResponse
    {
        $city = City::find($id);

        if (!$city) {
            return ApiResponse::notFound('المدينة غير موجودة');
        }

        $city->update($request->validated());

        return ApiResponse::success([
            'id' => $city->id,
            'name' => $city->name,
            'code' => $city->code,
            'created_at' => $city->created_at?->toIso8601String(),
            'updated_at' => $city->updated_at?->toIso8601String(),
        ], 'تم تحديث المدينة بنجاح');
    }

    public function destroy(int $id): JsonResponse
    {
        $city = City::find($id);

        if (!$city) {
            return ApiResponse::notFound('المدينة غير موجودة');
        }

        $city->delete();

        return ApiResponse::success(null, 'تم حذف المدينة بنجاح');
    }

    /**
     * Upload CSV for bulk cities + districts import.
     * Runs processing synchronously so the response includes final status, row_errors, and error_message.
     */
    public function import_csv(ImportCitiesDistrictsCsv $request): JsonResponse
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
        $missing = array_diff(['city_name', 'city_code'], $header);

        if (!empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'CSV is missing required columns.',
                'missing_columns' => array_values($missing),
            ], 422);
        }

        $storedPath = $file->store('csv-imports', 'local');

        $csvImport = CsvImport::create([
            'type' => CsvImport::TYPE_CITIES_DISTRICTS,
            'uploaded_by' => Auth::id(),
            'file_path' => $storedPath,
            'status' => CsvImport::STATUS_PENDING,
        ]);

        try {
            ProcessCitiesDistrictsCsv::dispatchSync($csvImport->id);
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
}
