<?php

use App\Http\Middleware\AssignPendingSlots;
use App\Http\Middleware\RoleMiddleware;
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
        // Di Vercel, semua permintaan lewat proxy Vercel: percayai agar IP pengunjung terbaca benar
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role'   => RoleMiddleware::class,
            'assign' => AssignPendingSlots::class,
        ]);

        // API tidak punya halaman login: jangan dialihkan, cukup balas 401
        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua error di /api/* selalu dibalas JSON (frontend tidak wajib kirim header Accept)
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();