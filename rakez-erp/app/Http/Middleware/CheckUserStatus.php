<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'trashed') && $user->trashed()) {
            return response()->json(['error' => 'Your account is no longer available. Please contact support.'], 403);
        }

        if ($user && ! $user->is_active) {
            return response()->json(['error' => 'Your account is deactivated. Please contact support.'], 403);
        }

        return $next($request);
    }
}
