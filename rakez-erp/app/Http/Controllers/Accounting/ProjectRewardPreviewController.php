<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\PreviewProjectRewardRequest;
use App\Models\SalesReservation;
use App\Services\Accounting\ProjectRewardPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProjectRewardPreviewController extends Controller
{
    public function __construct(
        private ProjectRewardPreviewService $previewService,
    ) {}

    public function previewReservationReward(
        PreviewProjectRewardRequest $request,
        SalesReservation $reservation,
    ): JsonResponse {
        try {
            $payload = $this->previewService->preview($reservation, $request->previewOptions());

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }
    }
}
