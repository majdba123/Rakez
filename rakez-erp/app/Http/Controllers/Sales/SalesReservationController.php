<?php

namespace App\Http\Controllers\Sales;

use App\Exceptions\UnitAlreadyReservedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CancelReservationRequest;
use App\Http\Requests\Sales\StoreReservationActionRequest;
use App\Http\Requests\Sales\StoreReservationRequest;
use App\Http\Resources\Sales\ReservationContextResource;
use App\Http\Resources\Sales\SalesReservationDetailResource;
use App\Http\Resources\Sales\SalesReservationResource;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Services\Sales\SalesReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SalesReservationController extends Controller
{
    public function __construct(
        private SalesReservationService $reservationService
    ) {}

    /**
     * Get reservation context for a unit.
     */
    public function context(int $unitId): JsonResponse
    {
        try {
            $unit = ContractUnit::with(['secondPartyData.contract'])->findOrFail($unitId);
            
            return response()->json([
                'success' => true,
                'data' => new ReservationContextResource($unit),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unit not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create a new reservation.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        try {
            $reservation = $this->reservationService->createReservation(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Reservation created successfully',
                'data' => new SalesReservationResource($reservation),
            ], 201);
        } catch (UnitAlreadyReservedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reservation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List reservations.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'mine' => $request->query('mine'),
                'include_cancelled' => $request->query('include_cancelled'),
                'contract_id' => $request->query('contract_id'),
                'status' => $request->query('status'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'per_page' => $request->query('per_page', 15),
            ];

            $reservations = $this->reservationService->getReservations($filters, $request->user());

            return response()->json([
                'success' => true,
                'data' => SalesReservationResource::collection($reservations->items()),
                'meta' => [
                    'current_page' => $reservations->currentPage(),
                    'last_page' => $reservations->lastPage(),
                    'per_page' => $reservations->perPage(),
                    'total' => $reservations->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reservations: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm a reservation.
     */
    public function confirm(int $id): JsonResponse
    {
        try {
            $reservation = $this->reservationService->confirmReservation($id, request()->user());

            return response()->json([
                'success' => true,
                'message' => 'Reservation confirmed successfully',
                'data' => new SalesReservationResource($reservation),
            ]);
        } catch (\Exception $e) {
            $statusCode = 400;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException || str_contains($e->getMessage(), 'Unauthorized')) {
                $statusCode = 403;
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm reservation: ' . $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Cancel a reservation.
     */
    public function cancel(int $id, CancelReservationRequest $request): JsonResponse
    {
        try {
            $reservation = $this->reservationService->cancelReservation(
                $id,
                $request->cancellation_reason ?? null,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Reservation cancelled successfully',
                'data' => new SalesReservationResource($reservation),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel reservation: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Log an action on a reservation.
     */
    public function storeAction(int $id, StoreReservationActionRequest $request): JsonResponse
    {
        try {
            $action = $this->reservationService->logAction(
                $id,
                $request->action_type,
                $request->notes ?? null,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Action logged successfully',
                'data' => [
                    'action_id' => $action->id,
                    'action_type' => $action->action_type,
                    'created_at' => $action->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to log action: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download reservation voucher.
     */
    public function downloadVoucher(int $id): Response
    {
        try {
            $reservation = SalesReservation::findOrFail($id);

            // Check authorization
            $user = request()->user();
            if ($reservation->marketing_employee_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to download this voucher',
                ], 403);
            }

            if (!$reservation->voucher_pdf_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher not found',
                ], 404);
            }

            $filePath = Storage::disk('public')->path($reservation->voucher_pdf_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher file not found',
                ], 404);
            }

            return response()->download(
                $filePath,
                "reservation_{$reservation->id}_voucher.pdf",
                [
                    'Content-Type' => 'application/pdf',
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download voucher: ' . $e->getMessage(),
            ], 500);
        }
    }
}
