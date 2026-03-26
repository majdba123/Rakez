<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\ApproveMontageDepartmentRequest;
use App\Http\Requests\Contract\StoreMontageDepartmentRequest;
use App\Http\Requests\Contract\UpdateMontageDepartmentRequest;
use App\Http\Resources\Contract\MontageDepartmentResource;
use App\Services\Contract\MontageDepartmentService;
use App\Events\Marketing\ImageUploadedEvent;
use Illuminate\Http\JsonResponse;
use Exception;
use App\Models\Team;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
/**
 * قسم المونتاج - Montage Department Controller
 */
class MontageDepartmentController extends Controller
{
    protected MontageDepartmentService $montageDepartmentService;

    public function __construct(MontageDepartmentService $montageDepartmentService)
    {
        $this->montageDepartmentService = $montageDepartmentService;
    }

    public function team_index(Request $request): JsonResponse
    {
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);
            $perPage = ApiResponse::getPerPage($request);

            $teams = Team::with(['members', 'contracts'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $teamsData = $teams->getCollection()->map(function ($team) use ($year, $month) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'members_count' => $team->members->count(),
                    'marketers' => $team->marketers()->get()->map(fn($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'phone' => $m->phone,
                    ]),
                    'created_at' => $team->created_at,
                ];
            });

            $teams->setCollection($teamsData);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة الفرق بنجاح',
                'data' => $teams->items(),
                'meta' => [
                    'pagination' => [
                        'total' => $teams->total(),
                        'count' => $teams->count(),
                        'per_page' => $teams->perPage(),
                        'current_page' => $teams->currentPage(),
                        'total_pages' => $teams->lastPage(),
                        'has_more_pages' => $teams->hasMorePages(),
                    ],
                    'period' => [
                        'year' => $year,
                        'month' => $month,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreMontageDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $montageDepartment = $this->montageDepartmentService->store($contractId, $data);

            if (isset($data['image_url'])) {
                event(new ImageUploadedEvent($contractId, $data['image_url'], 'montage'));
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات قسم المونتاج بنجاح',
                'data' => new MontageDepartmentResource($montageDepartment),
            ], 201);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'موجودة بالفعل') ? 422 : 500;
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : $statusCode;
            $statusCode = str_contains($e->getMessage(), 'يجب أن يكون') ? 422 : $statusCode;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function update(UpdateMontageDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $montageDepartment = $this->montageDepartmentService->updateByContractId($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات قسم المونتاج بنجاح',
                'data' => new MontageDepartmentResource($montageDepartment),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير موجودة') ? 404 : 500;
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : $statusCode;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function show(int $contractId): JsonResponse
    {
        try {
            $montageDepartment = $this->montageDepartmentService->getByContractId($contractId);

            if (!$montageDepartment) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات قسم المونتاج غير موجودة لهذا العقد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new MontageDepartmentResource($montageDepartment),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Approve or reject montage department (project_management manager or admin only)
     * اعتماد أو رفض بيانات قسم المونتاج — يلزم JSON: approved (bool)، وعند الرفض comment (سبب الرفض)
     */
    public function approve(ApproveMontageDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $validated = $request->validated();
            $approved = (bool) $validated['approved'];
            $comment = isset($validated['comment']) ? (string) $validated['comment'] : null;

            $montageDepartment = $this->montageDepartmentService->approveByContractId(
                $contractId,
                $approved,
                $comment
            );

            return response()->json([
                'success' => true,
                'message' => $approved
                    ? 'تم اعتماد بيانات قسم المونتاج بنجاح'
                    : 'تم رفض بيانات قسم المونتاج',
                'data' => new MontageDepartmentResource($montageDepartment),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير موجودة') ? 404 : 500;
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : $statusCode;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}

