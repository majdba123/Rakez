<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inventory Middleware
 * صلاحيات المخزون
 */
class InventoryMiddleware
{
    /**
     * Handle an incoming request.
     * Only allows users with type 'inventory' or 'admin'
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

        $allowedTypes = ['inventory', 'admin'];

        if (!in_array($user->type, $allowedTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - هذه الصلاحية متاحة فقط للمخزون',
            ], 403);
        }

        return $next($request);
    }
}


