<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Services\Contract\DeveloperService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class DeveloperController extends Controller
{
    public function __construct(
        protected DeveloperService $developerService
    ) {
    }

    /**
     * List developers (unique by developer_number + developer_name) with projects, units, teams.
     * GET /api/developers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح - يرجى تسجيل الدخول',
                ], 401);
            }

            $this->authorize('viewAny', \App\Models\Contract::class);

            $search = $request->input('search');
            $perPage = min((int) $request->input('per_page', 15), 100);
            $page = max(1, (int) $request->input('page', 1));

            $paginator = $this->developerService->getDevelopers($user, $search, $perPage, $page);

            $message = $paginator->total() === 0
                ? 'لا يوجد مطورين مطابقين للبحث'
                : 'تم جلب قائمة المطورين بنجاح';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $paginator->items(),
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ], 200);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'غير مصرح لهذا الإجراء',
            ], 403);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get one developer by developer_number (detail view). Same auth as list.
     * GET /api/developers/{developer_number}
     */
    public function show(Request $request, string $developerNumber): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح - يرجى تسجيل الدخول',
                ], 401);
            }

            $this->authorize('viewAny', \App\Models\Contract::class);

            $developerNumber = urldecode($developerNumber);
            $developer = $this->developerService->getDeveloperByNumber($developerNumber, $user);

            if ($developer === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على بيانات المطور. ربما تم فتح الرابط مباشرة',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات المطور بنجاح',
                'data' => $developer,
            ], 200);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'غير مصرح لهذا الإجراء',
            ], 403);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
