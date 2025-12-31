<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ProjectManagementMiddleware
{
    /**
     * Handle an incoming request.
     * Only allows users with type 'project_management' or 'admin'
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

        // Allow project_management and admin users
        $allowedTypes = ['project_management', 'admin'];

        if (!in_array($user->type, $allowedTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - هذه الصلاحية متاحة فقط لإدارة المشاريع',
            ], 403);
        }

        return $next($request);
    }
}

