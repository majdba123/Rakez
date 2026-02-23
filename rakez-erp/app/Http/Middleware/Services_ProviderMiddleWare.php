<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Services_ProviderMiddleWare
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // التحقق من وجود المستخدم ووجود Provider_service
        if ($user && !$user->Provider_service) {
            return response()->json(['error' => 'You are not a Service Provider'], 403);
        }

        // إذا كان المستخدم مزود خدمة، نتحقق من حالته
        if ($user && $user->Provider_service) {
            $status = $user->Provider_service->status;

            switch ($status) {
                case 'pending':
                    return response()->json([
                        'error' => 'Your account is pending admin approval',
                        'status' => 'pending'
                    ], 403);

                case 'pand':
                    return response()->json([
                        'error' => 'Your account has been pand',
                        'status' => 'pand'
                    ], 403);

                case 'active':
                    return $next($request);

                default:
                    return response()->json([
                        'error' => 'Your account status is invalid',
                        'status' => $status
                    ], 403);
            }
        }

        return $next($request);
    }
}
