<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admins: always allowed. Others: is_executive_director + sales|sales_leader (see User::isSalesExecutiveDirector).
 */
class EnsureSalesExecutiveDirector
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - يرجى تسجيل الدخول',
            ], 401);
        }

        if (! $user->canAccessSalesExecutiveAvailableUnitsApi()) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - هذه الواجهة مخصصة للإدمن أو مدراء المبيعات التنفيذيين فقط.',
            ], 403);
        }

        return $next($request);
    }
}
