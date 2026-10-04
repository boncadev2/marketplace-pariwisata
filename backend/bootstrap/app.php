<?php

use App\Http\Middleware\AdminAuthMiddleware;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\RequireSensitiveConfirmation;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ServiceManagementMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->prepend(AttachRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin.platform' => AdminAuthMiddleware::class,
            'service.management' => ServiceManagementMiddleware::class,
            'sensitive.confirmed' => RequireSensitiveConfirmation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
