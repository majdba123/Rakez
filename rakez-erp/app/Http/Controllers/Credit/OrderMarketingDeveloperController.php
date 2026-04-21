<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\OrderMarketingDeveloper;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderMarketingDeveloperController extends Controller
{
    /**
     * List marketing developer orders.
     * GET /credit/order-marketing-developers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $paginator = OrderMarketingDeveloper::with(['createdBy:id,name', 'updatedBy:id,name'])
                ->orderByDesc('id')
                ->paginate($perPage);

            $data = $paginator->getCollection()->map(fn (OrderMarketingDeveloper $row) => $this->transformRow($row));

            return response()->json([
                'success' => true,
                'message' => 'تم جلب السجلات بنجاح',
                'data' => $data->values()->all(),
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ], 200);
        } catch (ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a marketing developer order row.
     * POST /credit/order-marketing-developers
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->applyDeveloperFieldAliases($request);
            $validated = $request->validate([
                'developer_name' => ['required', 'string', 'max:255'],
                'developer_number' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'location' => ['nullable', 'string', 'max:255'],
            ]);
            $user = $request->user();

            $row = OrderMarketingDeveloper::create([
                'developer_name' => $validated['developer_name'],
                'developer_number' => $validated['developer_number'],
                'description' => $validated['description'] ?? null,
                'location' => $validated['location'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $row->load(['createdBy:id,name', 'updatedBy:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء السجل بنجاح',
                'data' => $this->transformRow($row),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /credit/order-marketing-developers/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $row = OrderMarketingDeveloper::with(['createdBy:id,name', 'updatedBy:id,name'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب السجل بنجاح',
                'data' => $this->transformRow($row),
            ], 200);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /credit/order-marketing-developers/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $this->applyDeveloperFieldAliases($request);
            if (!$request->hasAny(['developer_name', 'developer_number', 'description', 'location', 'name', 'number'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم إرسال أي حقول للتحديث',
                ], 422);
            }
            $validated = $request->validate([
                'developer_name' => ['sometimes', 'required', 'string', 'max:255'],
                'developer_number' => ['sometimes', 'required', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
                'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            ]);

            $row = OrderMarketingDeveloper::findOrFail($id);
            $payload = array_merge(
                array_intersect_key($validated, array_flip(['developer_name', 'developer_number', 'description', 'location'])),
                ['updated_by' => $request->user()->id]
            );
            $row->update($payload);
            $row->load(['createdBy:id,name', 'updatedBy:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث السجل بنجاح',
                'data' => $this->transformRow($row->fresh(['createdBy:id,name', 'updatedBy:id,name'])),
            ], 200);
        } catch (ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /credit/order-marketing-developers/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $row = OrderMarketingDeveloper::findOrFail($id);
            $row->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف السجل بنجاح',
            ], 200);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformRow(OrderMarketingDeveloper $row): array
    {
        return [
            'id' => $row->id,
            'developer_name' => $row->developer_name,
            'developer_number' => $row->developer_number,
            'description' => $row->description,
            'location' => $row->location,
            'created_by' => $row->createdBy ? [
                'id' => $row->createdBy->id,
                'name' => $row->createdBy->name,
            ] : null,
            'updated_by' => $row->updatedBy ? [
                'id' => $row->updatedBy->id,
                'name' => $row->updatedBy->name,
            ] : null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Accept `name` / `number` as aliases for developer_name / developer_number.
     */
    protected function applyDeveloperFieldAliases(Request $request): void
    {
        if (!$request->filled('developer_name') && $request->filled('name')) {
            $request->merge(['developer_name' => $request->input('name')]);
        }
        if (!$request->filled('developer_number') && $request->filled('number')) {
            $request->merge(['developer_number' => $request->input('number')]);
        }
    }
}
