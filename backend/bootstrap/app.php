<?php
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            if ($domain = config('portal.admin_domain')) {
                Route::domain($domain)->group(fn () => Route::redirect('/', '/admin'));
            }
            if ($domain = config('portal.entity_domain')) {
                Route::domain($domain)->group(fn () => Route::redirect('/', '/entidade'));
            }
        },
    )
    ->withMiddleware(function ($middleware) {
        $middleware->api();
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(fn ($exceptions) => null)
    ->create();
