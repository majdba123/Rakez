<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Credit\ClaimFileService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClaimFileController extends Controller
{
    protected ClaimFileService $claimFileService;

    public function __construct(ClaimFileService $claimFileService)
    {
        $this->claimFileService = $claimFileService;
    }

    /**
     * List claim files (Tab 5: إصدار ملف المطالبة والإفراغات).
     * GET /credit/claim-files
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $claimFiles = $this->claimFileService->listClaimFiles($perPage);

            $data = $claimFiles->getCollection()->map(function ($cf) {
                $status = $cf->hasPdf() ? 'completed' : 'under_processing';
                $statusLabelAr = $cf->hasPdf() ? 'مكتمل' : 'قيد المعالجة';
                $hasPdf = $cf->hasPdf();

                $row = [
                    'id' => $cf->id,
                    'is_combined' => $cf->isCombined(),
                    'status' => $status,
                    'status_label_ar' => $statusLabelAr,
                    'file_data' => $cf->file_data,
                    'has_pdf' => $hasPdf,
                    'created_at' => $cf->created_at,
                    'pdf_download_path' => $hasPdf ? "credit/claim-files/{$cf->id}/pdf" : null,
                ];

                if ($cf->isCombined()) {
                    $row['claim_type'] = $cf->claim_type;
                    $row['notes'] = $cf->notes;
                    $row['total_claim_amount'] = $cf->total_claim_amount;
                    $row['reservation_count'] = count($cf->file_data['items'] ?? []);
                    $row['project_name'] = $cf->file_data['summary']['project_name'] ?? null;
                } else {
                    $row['reservation_id'] = $cf->sales_reservation_id;
                    $row['project_name'] = $cf->reservation?->contract?->project_name
                        ?? $cf->reservation?->contract?->info?->project_name ?? null;
                    $row['unit_id'] = $cf->reservation?->contract_unit_id;
                    $row['unit_number'] = $cf->reservation?->contractUnit?->unit_number ?? null;
                    $row['claim_amount'] = $this->computeClaimAmount(
                        $cf->file_data['unit_price'] ?? null,
                        $cf->file_data['brokerage_commission_percent'] ?? null
                    );
                }

                return $row;
            });

            return response()->json([
                'success' => true,
                'message' => 'تم جلب ملفات المطالبة بنجاح',
                'data' => $data->values()->all(),
                'meta' => [
                    'total' => $claimFiles->total(),
                    'per_page' => $claimFiles->perPage(),
                    'current_page' => $claimFiles->currentPage(),
                    'last_page' => $claimFiles->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List candidates for creating claim files (sold reservations without a claim file).
     * GET /credit/claim-files/candidates
     */
    public function candidates(Request $request): JsonResponse
    {
        try {
            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $candidates = $this->claimFileService->listClaimFileCandidates($perPage);

            $data = $candidates->getCollection()->map(function ($reservation) {
                $price = $reservation->proposed_price ?? $reservation->contractUnit?->price ?? null;
                $claimAmount = $this->computeClaimAmount($price, $reservation->brokerage_commission_percent);
                return [
                    'reservation_id' => $reservation->id,
                    'project_name' => $reservation->contract?->project_name ?? $reservation->contract?->info?->project_name ?? null,
                    'unit_id' => $reservation->contract_unit_id,
                    'unit_number' => $reservation->contractUnit?->unit_number ?? null,
                    'claim_amount' => $claimAmount,
                    'status' => $reservation->credit_status ?? 'sold',
                    'status_label_ar' => $this->creditStatusLabelAr($reservation->credit_status),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'تم جلب المرشحين لإنشاء ملف المطالبة بنجاح',
                'data' => $data->values()->all(),
                'meta' => [
                    'total' => $candidates->total(),
                    'per_page' => $candidates->perPage(),
                    'current_page' => $candidates->currentPage(),
                    'last_page' => $candidates->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate claim files for multiple reservations (bulk).
     * POST /credit/claim-files/generate-bulk
     */
    public function generateBulk(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reservation_ids' => 'required|array',
                'reservation_ids.*' => 'integer|min:1',
            ], [
                'reservation_ids.required' => 'معرفات الحجوزات مطلوبة',
            ]);

            $result = $this->claimFileService->generateClaimFilesBulk(
                $validated['reservation_ids'],
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'تم معالجة الطلب',
                'data' => [
                    'created' => $result['created'],
                    'errors' => $result['errors'],
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a single combined claim file from multiple reservations.
     * POST /credit/claim-files/combined
     */
    public function generateCombined(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'booking_ids' => 'required|array|min:2',
                'booking_ids.*' => 'integer|min:1',
                'claim_type' => 'required|string|in:commission',
                'notes' => 'nullable|string|max:1000',
            ], [
                'booking_ids.required' => 'معرفات الحجوزات مطلوبة',
                'booking_ids.min' => 'يجب اختيار حجزين على الأقل للدمج',
                'claim_type.required' => 'نوع المطالبة مطلوب',
                'claim_type.in' => 'نوع المطالبة غير صالح',
            ]);

            $claimFile = $this->claimFileService->generateCombinedClaimFile(
                $validated['booking_ids'],
                $validated['claim_type'],
                $validated['notes'] ?? null,
                $request->user()
            );

            $items = collect($claimFile->file_data['items'] ?? [])->map(function ($item) {
                return [
                    'reservation_id' => $item['reservation_id'] ?? null,
                    'project_name' => $item['project_name'] ?? null,
                    'unit_number' => $item['unit_number'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'claim_amount' => $this->computeClaimAmount(
                        $item['unit_price'] ?? null,
                        $item['brokerage_commission_percent'] ?? null
                    ),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء ملف المطالبة المجمع بنجاح',
                'data' => [
                    'id' => $claimFile->id,
                    'is_combined' => true,
                    'claim_type' => $claimFile->claim_type,
                    'notes' => $claimFile->notes,
                    'total_claim_amount' => $claimFile->total_claim_amount,
                    'reservation_count' => count($claimFile->file_data['items'] ?? []),
                    'items' => $items->all(),
                    'has_pdf' => $claimFile->hasPdf(),
                    'created_at' => $claimFile->created_at,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
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

            $data = [
                'id' => $claimFile->id,
                'is_combined' => $claimFile->isCombined(),
                'file_data' => $claimFile->file_data,
                'has_pdf' => $claimFile->hasPdf(),
                'created_at' => $claimFile->created_at,
                'generated_by' => $claimFile->generatedBy,
            ];

            if ($claimFile->isCombined()) {
                $data['claim_type'] = $claimFile->claim_type;
                $data['notes'] = $claimFile->notes;
                $data['total_claim_amount'] = $claimFile->total_claim_amount;
                $data['reservation_count'] = count($claimFile->file_data['items'] ?? []);
                $data['items'] = collect($claimFile->file_data['items'] ?? [])->map(function ($item) {
                    return [
                        'reservation_id' => $item['reservation_id'] ?? null,
                        'project_name' => $item['project_name'] ?? null,
                        'unit_number' => $item['unit_number'] ?? null,
                        'unit_price' => $item['unit_price'] ?? null,
                        'claim_amount' => $this->computeClaimAmount(
                            $item['unit_price'] ?? null,
                            $item['brokerage_commission_percent'] ?? null
                        ),
                    ];
                })->all();
            } else {
                $data['reservation_id'] = $claimFile->sales_reservation_id;
                $data['reservation'] = $claimFile->reservation;
            }

            return response()->json([
                'success' => true,
                'message' => 'تم جلب ملف المطالبة بنجاح',
                'data' => $data,
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

            $downloadName = sprintf('claim_file_%d_%s.pdf', $claimFile->id, $claimFile->created_at?->format('Y-m-d') ?? date('Y-m-d'));
            return Storage::disk('public')->download($claimFile->pdf_path, $downloadName);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Arabic label for credit_status (for candidates list).
     */
    private function creditStatusLabelAr(?string $status): string
    {
        return match ($status) {
            'pending' => 'قيد الانتظار',
            'in_progress' => 'قيد التنفيذ',
            'title_transfer' => 'نقل الملكية',
            'sold' => 'مباع',
            'rejected' => 'مرفوض',
            default => $status ?? '—',
        };
    }

    /**
     * Compute claim amount (مبلغ المطالبة): price × (brokerage_commission_percent / 100).
     * Returns numeric value or null if either input is missing.
     */
    private function computeClaimAmount($price, $brokerageCommissionPercent): ?float
    {
        $p = $price !== null && $price !== '' ? (float) $price : null;
        $c = $brokerageCommissionPercent !== null && $brokerageCommissionPercent !== '' ? (float) $brokerageCommissionPercent : null;
        if ($p === null || $c === null) {
            return null;
        }
        return round($p * $c / 100, 2);
    }
}



