<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Percayai header X-Forwarded-* dari proxy (ngrok, Cloudflare, load balancer cPanel)
        // agar URL CSS/JS ikut https dan tidak diblokir browser (mixed content).
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        $middleware->web(append: [SetLocale::class]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => auth()->user()?->homeUrl() ?? route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
