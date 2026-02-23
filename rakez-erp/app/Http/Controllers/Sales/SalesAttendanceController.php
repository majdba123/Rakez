<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreAttendanceScheduleRequest;
use App\Http\Resources\Sales\SalesAttendanceResource;
use App\Services\Sales\SalesAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesAttendanceController extends Controller
{
    public function __construct(
        private SalesAttendanceService $attendanceService
    ) {}

    /**
     * List my attendance schedules.
     */
    public function my(Request $request): JsonResponse
    {
        try {
            $filters = [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ];

            $schedules = $this->attendanceService->getMySchedules($filters, $request->user());

            return response()->json([
                'success' => true,
                'data' => SalesAttendanceResource::collection($schedules),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedules: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List team attendance schedules (leader only).
     */
    public function team(Request $request): JsonResponse
    {
        try {
            $filters = [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'contract_id' => $request->query('contract_id'),
                'user_id' => $request->query('user_id'),
            ];

            $schedules = $this->attendanceService->getTeamSchedules($filters, $request->user());

            return response()->json([
                'success' => true,
                'data' => SalesAttendanceResource::collection($schedules),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team schedules: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new attendance schedule (leader only).
     */
    public function store(StoreAttendanceScheduleRequest $request): JsonResponse
    {
        try {
            $schedule = $this->attendanceService->createSchedule(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Schedule created successfully',
                'data' => new SalesAttendanceResource($schedule),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create schedule: ' . $e->getMessage(),
            ], 400);
        }
    }
}
