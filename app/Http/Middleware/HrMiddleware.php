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

        if (!in_array($user->type, $allowedTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - هذه الصلاحية متاحة فقط للموارد البشرية',
            ], 403);
        }

        return $next($request);
    }
}


