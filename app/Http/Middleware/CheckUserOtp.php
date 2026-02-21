<?php

namespace App\Http\Middleware;

use App\Helpers\OtpHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckUserOtp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if (($user->otp ?? 0) == 1 || OtpHelper::isOtpVerified($user->id)) {

            return $next($request);
        }

        return response()->json(['error' => 'OTP verification is required'], 401);
    }
}
