<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CheckUserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use stdClass;
use Tests\TestCase;

class CheckUserStatusTest extends TestCase
{
    public function test_allows_request_when_no_authenticated_user(): void
    {
        Auth::shouldReceive('user')->andReturn(null);

        $request = Request::create('/test', 'GET');
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['ok' => true]);
        };

        $middleware = new CheckUserStatus();
        $response = $middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_allows_request_when_user_is_active(): void
    {
        $user = new stdClass;
        $user->is_active = true;
        Auth::shouldReceive('user')->andReturn($user);

        $request = Request::create('/test', 'GET');
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['ok' => true]);
        };

        $middleware = new CheckUserStatus();
        $response = $middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_returns_401_with_error_code_when_user_is_inactive(): void
    {
        $user = new stdClass;
        $user->is_active = false;
        Auth::shouldReceive('user')->andReturn($user);

        $request = Request::create('/test', 'GET');
        $next = function () {
            return response()->json(['ok' => true]);
        };

        $middleware = new CheckUserStatus();
        $response = $middleware->handle($request, $next);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('ACCOUNT_SUSPENDED', $data['error_code']);
        $this->assertStringContainsString('suspended', $data['message']);
    }
}
