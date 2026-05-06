<?php

namespace App\Http\Controllers\Accounting;

use App\Exceptions\Accounting\UnitPriceUnresolvedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\PreviewProjectCommissionRequest;
use App\Http\Requests\Accounting\PreviewUnitCommissionRequest;
use App\Models\Contract;
use App\Models\SalesReservation;
use App\Services\Accounting\ProjectCommissionPreviewService;
use App\Services\Accounting\UnitCommissionPreviewService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ProjectCommissionPreviewController extends Controller
{
    public function __construct(
        private UnitCommissionPreviewService $unitCommissionPreviewService,
        private ProjectCommissionPreviewService $projectCommissionPreviewService,
    ) {}

    /**
     * POST /api/accounting/projects/{project}/preview-commission
     */
    public function previewProject(PreviewProjectCommissionRequest $request, Contract $project): JsonResponse
    {
        try {
            $payload = $this->projectCommissionPreviewService->previewProject($project, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/accounting/reservations/{reservation}/preview-unit-commission
     */
    public function previewUnit(PreviewUnitCommissionRequest $request, SalesReservation $reservation): JsonResponse
    {
        try {
            $payload = $this->unitCommissionPreviewService->preview(
                $reservation,
                $request->previewOptions(),
            );

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (UnitPriceUnresolvedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['unit_price' => [$e->getMessage()]],
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
