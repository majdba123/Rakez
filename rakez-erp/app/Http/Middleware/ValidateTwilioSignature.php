<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

class ValidateTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $token = config('ai_calling.twilio.token');
        if (empty($token)) {
            abort(500, 'Twilio auth token not configured.');
        }

        $signature = $request->header('X-Twilio-Signature', '');
        if (empty($signature)) {
            abort(403, 'Missing Twilio signature.');
        }

        $url = $request->fullUrl();
        $params = $request->isMethod('POST') ? $request->post() : [];

        $validator = new RequestValidator($token);

        if (! $validator->validate($signature, $url, $params)) {
            abort(403, 'Invalid Twilio signature.');
        }

        return $next($request);
    }
}
