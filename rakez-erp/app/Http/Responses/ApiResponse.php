<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    /**
     * Return a success response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'تمت العملية بنجاح',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a created response (201).
     */
    public static function created(
        mixed $data = null,
        string $message = 'تم الإنشاء بنجاح'
    ): JsonResponse {
        return self::success($data, $message, 201);
    }

    /**
     * Return an error response.
     */
    public static function error(
        string $message = 'حدث خطأ',
        int $statusCode = 400,
        ?string $errorCode = null,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode) {
            $response['error_code'] = $errorCode;
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error response (422).
     */
    public static function validationError(
        array $errors,
        string $message = 'خطأ في التحقق من البيانات'
    ): JsonResponse {
        return self::error($message, 422, 'VALIDATION_ERROR', $errors);
    }

    /**
     * Return an unauthorized response (401).
     */
    public static function unauthorized(
        string $message = 'غير مصرح - يجب تسجيل الدخول'
    ): JsonResponse {
        return self::error($message, 401, 'UNAUTHORIZED');
    }

    /**
     * Return a forbidden response (403).
     */
    public static function forbidden(
        string $message = 'ممنوع - صلاحيات غير كافية'
    ): JsonResponse {
        return self::error($message, 403, 'FORBIDDEN');
    }

    /**
     * Return a not found response (404).
     */
    public static function notFound(
        string $message = 'غير موجود'
    ): JsonResponse {
        return self::error($message, 404, 'NOT_FOUND');
    }

    /**
     * Return a conflict response (409).
     */
    public static function conflict(
        string $message = 'تعارض في البيانات',
        ?string $errorCode = null
    ): JsonResponse {
        return self::error($message, 409, $errorCode ?? 'CONFLICT');
    }

    /**
     * Return a server error response (500).
     */
    public static function serverError(
        string $message = 'خطأ في الخادم'
    ): JsonResponse {
        return self::error($message, 500, 'SERVER_ERROR');
    }

    /**
     * Get validated per_page from request (default 15, max 100).
     *
     * @param \Illuminate\Http\Request $request
     * @param int $default
     * @param int $max
     * @return int
     */
    public static function getPerPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->input('per_page', $default);
        return min(max($perPage, 1), $max);
    }

    /**
     * Return a paginated response.
     */
    public static function paginated(
        $paginatedData,
        string $message = 'تمت العملية بنجاح'
    ): JsonResponse {
        return self::success(
            $paginatedData->items(),
            $message,
            200,
            [
                'pagination' => [
                    'total' => $paginatedData->total(),
                    'count' => $paginatedData->count(),
                    'per_page' => $paginatedData->perPage(),
                    'current_page' => $paginatedData->currentPage(),
                    'total_pages' => $paginatedData->lastPage(),
                    'has_more_pages' => $paginatedData->hasMorePages(),
                ],
            ]
        );
    }

    /**
     * Return a no content response (204).
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return an accepted response (202) for async operations.
     */
    public static function accepted(
        string $message = 'تم قبول الطلب وسيتم معالجته',
        mixed $data = null
    ): JsonResponse {
        return self::success($data, $message, 202);
    }
}
