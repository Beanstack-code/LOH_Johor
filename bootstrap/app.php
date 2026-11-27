<?php

use App\Http\Middleware\RequiresActiveSubscription;
use App\Http\Middleware\ResolveApiGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);

        $middleware->alias([
            'role'                  => RoleMiddleware::class,
            'permission'            => PermissionMiddleware::class,
            'role_or_permission'    => RoleOrPermissionMiddleware::class,
            'api.guard'             => ResolveApiGuard::class,
            'subscription.required' => RequiresActiveSubscription::class,
            'raw.body'              => \App\Http\Middleware\CaptureRawBody::class,
        ]);

        // Detect Accept-Language / X-App-Locale on every API request so
        // controllers can resolve translated fields via ApiLocale::resolve().
        $middleware->api(prepend: [
            \App\Http\Middleware\SetApiLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
