<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sales Middleware
 * صلاحيات المبيعات
 */
class SalesMiddleware
{
    /**
     * Handle an incoming request.
     * Only allows users with type 'sales' or 'admin'
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

        // Allow sales and admin users
        $allowedTypes = ['sales', 'admin'];

        if (!in_array($user->type, $allowedTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - هذه الصلاحية متاحة فقط للمبيعات',
            ], 403);
        }

        return $next($request);
    }
}


