<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreWaitingListRequest;
use App\Http\Requests\Sales\ConvertWaitingListRequest;
use App\Services\Sales\WaitingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WaitingListController extends Controller
{
    public function __construct(
        private WaitingListService $waitingListService
    ) {}

    /**
     * Get waiting list entries with filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->query('status'),
                'sales_staff_id' => $request->query('sales_staff_id'),
                'contract_id' => $request->query('contract_id'),
                'contract_unit_id' => $request->query('contract_unit_id'),
                'active_only' => $request->query('active_only', false),
            ];

            // Remove null filters
            $filters = array_filter($filters, fn($value) => $value !== null);

            $perPage = $request->query('per_page', 15);
            $waitingList = $this->waitingListService->getWaitingList($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $waitingList->items(),
                'meta' => [
                    'current_page' => $waitingList->currentPage(),
                    'last_page' => $waitingList->lastPage(),
                    'per_page' => $waitingList->perPage(),
                    'total' => $waitingList->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve waiting list: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get waiting list entries for a specific unit.
     */
    public function getByUnit(int $unitId): JsonResponse
    {
        try {
            $waitingList = $this->waitingListService->getWaitingListForUnit($unitId);

            return response()->json([
                'success' => true,
                'data' => $waitingList,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve waiting list: ' . $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Create a new waiting list entry.
     */
    public function store(StoreWaitingListRequest $request): JsonResponse
    {
        try {
            $waitingEntry = $this->waitingListService->createWaitingListEntry(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Waiting list entry created successfully',
                'data' => $waitingEntry,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create waiting list entry: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Convert waiting list entry to confirmed reservation (Leader only).
     */
    public function convert(int $id, ConvertWaitingListRequest $request): JsonResponse
    {
        try {
            $reservation = $this->waitingListService->convertToReservation(
                $id,
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Waiting list entry converted to reservation successfully',
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert waiting list entry: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Cancel a waiting list entry.
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        try {
            $waitingEntry = $this->waitingListService->cancelWaitingEntry($id, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Waiting list entry cancelled successfully',
                'data' => $waitingEntry,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel waiting list entry: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
