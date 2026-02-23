<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreWaitingListRequest;
use App\Http\Requests\Sales\ConvertWaitingListRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Sales\WaitingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $filters = [
            'status' => $request->query('status'),
            'sales_staff_id' => $request->query('sales_staff_id'),
            'contract_id' => $request->query('contract_id'),
            'contract_unit_id' => $request->query('contract_unit_id'),
            'active_only' => $request->query('active_only', false),
        ];
        $filters = array_filter($filters, fn($value) => $value !== null);
        $perPage = ApiResponse::getPerPage($request, 15, 100);
        $waitingList = $this->waitingListService->getWaitingList($filters, $perPage);
        $items = $waitingList->getCollection()->map(function ($entry) {
            $arr = $entry->toArray();
            $arr['project_name'] = $entry->contract?->project_name ?? $entry->contract?->info?->project_name ?? null;
            $arr['unit_number'] = $entry->contractUnit?->unit_number ?? null;
            return $arr;
        })->all();
        return ApiResponse::success(
            $items,
            'تم جلب قائمة الانتظار بنجاح',
            200,
            ['pagination' => ApiResponse::paginationMeta($waitingList)]
        );
    }

    /**
     * Get a single waiting list entry by id.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $entry = $this->waitingListService->getWaitingListEntry($id, request()->user());
            return ApiResponse::success($entry, 'تم جلب السجل بنجاح');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('سجل قائمة الانتظار غير موجود');
        } catch (\Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Get waiting list entries for a specific unit.
     */
    public function getByUnit(int $unitId): JsonResponse
    {
        try {
            $waitingList = $this->waitingListService->getWaitingListForUnit($unitId);
            return ApiResponse::success($waitingList, 'تم جلب قائمة الانتظار للوحدة بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::notFound($e->getMessage());
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
            return ApiResponse::created($waitingEntry, 'تم إضافة السجل إلى قائمة الانتظار بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
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
            return ApiResponse::success($reservation, 'تم تحويل سجل قائمة الانتظار إلى حجز بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Cancel a waiting list entry.
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        try {
            $waitingEntry = $this->waitingListService->cancelWaitingEntry($id, $request->user());
            return ApiResponse::success($waitingEntry, 'تم إلغاء سجل قائمة الانتظار بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
