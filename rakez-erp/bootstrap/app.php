<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckUserOtp;
use App\Http\Middleware\CheckUserStatus;
use App\Http\Middleware\EditorMiddleware;
use App\Http\Middleware\EnsureAiAssistantAccess;
use App\Http\Middleware\EnsureSalesLeader;
use App\Http\Middleware\HrMiddleware;
use App\Http\Middleware\InventoryMiddleware;
use App\Http\Middleware\MarketingMiddleware;
use App\Http\Middleware\ProjectManagementMiddleware;
use App\Http\Middleware\SalesMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// NOTE: app/Http/Kernel.php is a Laravel ≤10 artifact and is IGNORED by Laravel 12.
// ALL middleware aliases must be registered here in bootstrap/app.php.

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum: enable CSRF-protected stateful auth for SPA/Filament sessions.
        // Without this, a Filament admin session could authenticate API routes
        // without a Bearer token AND without CSRF verification.
        $middleware->statefulApi();

        $middleware->alias([
            // Domain / role middleware
            'admin'              => AdminMiddleware::class,
            'project_management' => ProjectManagementMiddleware::class,
            'sales_leader'       => EnsureSalesLeader::class,
            'hr'                 => HrMiddleware::class,
            'editor'             => EditorMiddleware::class,
            'inventory'          => InventoryMiddleware::class,
            'sales'              => SalesMiddleware::class,
            'marketing'          => MarketingMiddleware::class,

            // Auth
            'auth'               => \App\Http\Middleware\Authenticate::class,
            'check_status'       => CheckUserStatus::class,
            'otp'                => CheckUserOtp::class,

            // AI
            'ai.assistant'       => EnsureAiAssistantAccess::class,
            'ai.redact'          => \App\Http\Middleware\RedactPiiFromAi::class,

            // Spatie permission
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
    })->create();
