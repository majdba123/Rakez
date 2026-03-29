<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDistrictRequest;
use App\Http\Requests\Admin\UpdateDistrictRequest;
use App\Http\Responses\ApiResponse;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
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
