<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Sales Leader Middleware
 * Allows users whose type is 'sales_leader', or who are a sales manager (type='sales' + is_manager=true), or admin.
 * Used as the 'sales_leader' alias in bootstrap/app.php.
 */
class EnsureSalesLeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $isSalesLeader = $user->type === 'sales_leader'
            || ($user->type === 'sales' && $user->is_manager)
            || $user->hasRole('sales_leader');

        if (! $isSalesLeader && $user->type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Sales leader access required.',
            ], 403);
        }

        return $next($request);
    }
}
