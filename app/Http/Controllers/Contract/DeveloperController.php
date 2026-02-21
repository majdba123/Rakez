<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Contract\DeveloperService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                return ApiResponse::unauthorized('غير مصرح - يرجى تسجيل الدخول');
            }

            $this->authorize('viewAny', \App\Models\Contract::class);

            $search = $request->input('search');
            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $page = max(1, (int) $request->input('page', 1));

            $paginator = $this->developerService->getDevelopers($user, $search, $perPage, $page);

            $message = $paginator->total() === 0
                ? 'لا يوجد مطورين مطابقين للبحث'
                : 'تم جلب قائمة المطورين بنجاح';

            return ApiResponse::success($paginator->items(), $message, 200, [
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage() ?: 'غير مصرح لهذا الإجراء');
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
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
                return ApiResponse::unauthorized('غير مصرح - يرجى تسجيل الدخول');
            }

            $this->authorize('viewAny', \App\Models\Contract::class);

            $developerNumber = urldecode($developerNumber);
            $developer = $this->developerService->getDeveloperByNumber($developerNumber, $user);

            if ($developer === null) {
                return ApiResponse::notFound('لم يتم العثور على بيانات المطور. ربما تم فتح الرابط مباشرة');
            }

            return ApiResponse::success($developer, 'تم جلب بيانات المطور بنجاح');
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage() ?: 'غير مصرح لهذا الإجراء');
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }
}
