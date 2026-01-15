<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Editor Middleware
 * صلاحيات المحرر
 */
class EditorMiddleware
{
    /**
     * Handle an incoming request.
     * Only allows users with type 'editor' or 'admin'
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

        // Allow editor and admin users
        $allowedTypes = ['editor', 'admin'];

        if (!in_array($user->type, $allowedTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - هذه الصلاحية متاحة فقط للمحررين',
            ], 403);
        }

        return $next($request);
    }
}

