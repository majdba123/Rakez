<?php

namespace App\Http\Middleware;

use App\Constants\ApiErrorCodes;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     * Blocks inactive users (is_active === false) with 401.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user && $user->is_active === false) {
            return ApiResponse::error('Your account is suspended. Please contact support', 401, ApiErrorCodes::ACCOUNT_SUSPENDED);
        }

        return $next($request);
    }
}
