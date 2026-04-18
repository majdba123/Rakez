<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editor Middleware
 * Allows only users whose type is 'editor' or 'admin'.
 * Used as the 'editor' alias in bootstrap/app.php.
 */
class EditorMiddleware
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

        $allowed = config('user_types.middleware_allowed.editor', ['editor', 'admin']);

        if (! in_array($user->type, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Editor access required.',
            ], 403);
        }

        return $next($request);
    }
}
