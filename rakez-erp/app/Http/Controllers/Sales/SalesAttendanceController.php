<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\BulkAttendanceRequest;
use App\Http\Requests\Sales\StoreAttendanceScheduleRequest;
use App\Http\Resources\Sales\SalesAttendanceResource;
use App\Models\User;
use App\Services\Sales\SalesAttendanceService;
use App\Services\Sales\SalesNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesAttendanceController extends Controller
{
    public function __construct(
        private SalesAttendanceService $attendanceService,
        private SalesNotificationService $notificationService
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

    /**
     * Get project attendance overview for a given date (leader only).
     * Shows all team members with their presence status.
     */
    public function projectOverview(Request $request, int $contractId): JsonResponse
    {
        try {
            $date = $request->query('date', now()->toDateString());

            $overview = $this->attendanceService->getProjectAttendanceOverview(
                $contractId,
                $date,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $overview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve project attendance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk save attendance schedules for a project on a given day (leader only).
     * Sends notifications to affected team members.
     */
    public function bulkStore(BulkAttendanceRequest $request, int $contractId): JsonResponse
    {
        try {
            $result = $this->attendanceService->bulkSaveSchedules(
                $contractId,
                $request->validated(),
                $request->user()
            );

            $contract = $result['contract'];
            $date = $result['date'];

            foreach ($result['created'] as $entry) {
                $user = User::find($entry['user_id']);
                if ($user) {
                    $this->notificationService->notifyScheduleAssigned(
                        $user, $contract, $date, $entry['start_time'], $entry['end_time']
                    );
                }
            }

            foreach ($result['updated'] as $entry) {
                $user = User::find($entry['user_id']);
                if ($user) {
                    $this->notificationService->notifyScheduleAssigned(
                        $user, $contract, $date, $entry['start_time'], $entry['end_time']
                    );
                }
            }

            foreach ($result['removed'] as $entry) {
                $user = User::find($entry['user_id']);
                if ($user) {
                    $this->notificationService->notifyScheduleRemoved($user, $contract, $date);
                }
            }

            $totalChanges = count($result['created']) + count($result['updated']) + count($result['removed']);

            return response()->json([
                'success' => true,
                'message' => "Attendance saved. {$totalChanges} schedule(s) updated.",
                'data' => [
                    'created' => count($result['created']),
                    'updated' => count($result['updated']),
                    'removed' => count($result['removed']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save attendance: ' . $e->getMessage(),
            ], 400);
        }
    }
}
