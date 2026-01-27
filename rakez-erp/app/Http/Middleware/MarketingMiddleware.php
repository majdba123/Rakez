<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ($request->user()->type === 'marketing' || $request->user()->type === 'admin')) {
        return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Marketing role required.'
        ], 403);
    }
}
