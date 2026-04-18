<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetFilamentAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) config('governance.panel_locale', config('app.locale', 'en'));

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
