<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Services\Credit\ClaimFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;

class ClaimFileController extends Controller
{
    protected ClaimFileService $claimFileService;

    public function __construct(ClaimFileService $claimFileService)
    {
        $this->claimFileService = $claimFileService;
    }

    /**
     * Generate claim file for a reservation.
     * POST /credit/bookings/{id}/claim-file
     */
    public function generate(Request $request, int $id): JsonResponse
    {
        try {
            $claimFile = $this->claimFileService->generateClaimFile($id, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء ملف المطالبة بنجاح',
                'data' => [
                    'id' => $claimFile->id,
                    'reservation_id' => $claimFile->sales_reservation_id,
                    'file_data' => $claimFile->file_data,
                    'has_pdf' => $claimFile->hasPdf(),
                    'created_at' => $claimFile->created_at,
                ],
            ], 201);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get claim file details.
     * GET /credit/claim-files/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $claimFile = $this->claimFileService->getClaimFile($id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب ملف المطالبة بنجاح',
                'data' => $claimFile,
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Generate PDF for claim file.
     * POST /credit/claim-files/{id}/pdf
     */
    public function generatePdf(int $id): JsonResponse
    {
        try {
            $pdfPath = $this->claimFileService->generatePdf($id);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء ملف PDF بنجاح',
                'data' => [
                    'pdf_path' => $pdfPath,
                    'download_url' => Storage::disk('public')->url($pdfPath),
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Download claim file PDF.
     * GET /credit/claim-files/{id}/pdf
     */
    public function download(int $id)
    {
        try {
            $claimFile = $this->claimFileService->getClaimFile($id);

            if (empty($claimFile->pdf_path) || !Storage::disk('public')->exists($claimFile->pdf_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف PDF غير موجود، يرجى إنشاؤه أولاً',
                ], 404);
            }

            return Storage::disk('public')->download($claimFile->pdf_path);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}



