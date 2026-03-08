<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * HR Middleware
 * صلاحيات الموارد البشرية
 */
class HrMiddleware
{
    /**
     * Handle an incoming request.
     * Only allows users with type 'hr' or 'admin'
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - يرجى تسجيل الدخول',
            ], 401);
        }

        $allowedTypes = ['hr', 'admin'];

        if (in_array($user->type, $allowedTypes)) {
            return $next($request);
        }

        // GET /api/hr/users and GET /api/hr/users/{id}: allow if user has hr.users.view (e.g. marketing)
        $path = $request->path();
        $isHrUsersGet = $request->isMethod('GET')
            && preg_match('#^(api/)?hr/users(?:/\d+)?$#', $path);
        if ($isHrUsersGet && (method_exists($user, 'can') && $user->can('hr.users.view'))) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'غير مصرح - هذه الصلاحية متاحة فقط للموارد البشرية',
        ], 403);
    }
}


