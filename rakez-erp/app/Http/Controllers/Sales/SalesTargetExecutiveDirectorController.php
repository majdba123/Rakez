<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesTargetExecutiveDirectorRequest;
use App\Http\Requests\Sales\UpdateSalesTargetExecutiveDirectorRequest;
use App\Http\Resources\Sales\SalesTargetExecutiveDirectorResource;
use App\Models\SalesTarget;
use App\Models\SalesTargetExecutiveDirector;
use Illuminate\Http\JsonResponse;

class SalesTargetExecutiveDirectorController extends Controller
{
    /**
     * List executive-director lines for a sales target.
     *
     * GET /api/sales/targets/{salesTargetId}/executive-director-lines
     */
    public function index(int $salesTargetId): JsonResponse
    {
        SalesTarget::query()->findOrFail($salesTargetId);

        $rows = SalesTargetExecutiveDirector::query()
            ->where('sales_target_id', $salesTargetId)
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => SalesTargetExecutiveDirectorResource::collection($rows),
        ]);
    }

    /**
     * Create a line.
     *
     * POST /api/sales/targets/{salesTargetId}/executive-director-lines
     */
    public function store(StoreSalesTargetExecutiveDirectorRequest $request, int $salesTargetId): JsonResponse
    {
        $target = SalesTarget::query()->findOrFail($salesTargetId);
        $v = $request->validated();

        $row = $target->executiveDirectors()->create([
            'line_type' => $v['type'],
            'value' => $v['value'] ?? null,
            'status' => $v['status'] ?? 'pending',
        ]);

        $row->refresh();

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة سطر المدير التنفيذي.',
            'data' => new SalesTargetExecutiveDirectorResource($row),
        ], 201);
    }

    /**
     * Show one line.
     *
     * GET /api/sales/executive-director-lines/{id}
     */
    public function show(int $id): JsonResponse
    {
        $row = SalesTargetExecutiveDirector::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new SalesTargetExecutiveDirectorResource($row),
        ]);
    }

    /**
     * Update a line.
     *
     * PUT /api/sales/executive-director-lines/{id}
     */
    public function update(UpdateSalesTargetExecutiveDirectorRequest $request, int $id): JsonResponse
    {
        $row = SalesTargetExecutiveDirector::query()->findOrFail($id);
        $v = $request->validated();
        if (array_key_exists('type', $v)) {
            $row->line_type = $v['type'];
        }
        if (array_key_exists('value', $v)) {
            $row->value = $v['value'];
        }
        if (array_key_exists('status', $v)) {
            $row->status = $v['status'];
        }
        $row->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث سطر المدير التنفيذي.',
            'data' => new SalesTargetExecutiveDirectorResource($row->fresh()),
        ]);
    }

    /**
     * Delete a line.
     *
     * DELETE /api/sales/executive-director-lines/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $row = SalesTargetExecutiveDirector::query()->findOrFail($id);
        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف سطر المدير التنفيذي.',
        ]);
    }
}
