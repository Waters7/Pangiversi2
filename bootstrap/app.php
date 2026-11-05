<?php

use App\Http\Middleware\ApproverMiddleware;
use App\Http\Middleware\ModulDalamPengembangan;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\TokenApi;
use App\Http\Middleware\TokenMappalus;
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
        // Peran yang modulnya masih dikembangkan ditahan pada halaman
        // pemberitahuan; berlaku untuk seluruh rute web setelah masuk.
        $middleware->web(append: [ModulDalamPengembangan::class]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'approver' => ApproverMiddleware::class,
            'token.api' => TokenApi::class,
            'token.mappalus' => TokenMappalus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pemanggil API tidak selalu mengirim Accept: application/json; tanpa
        // ini galat validasinya menjadi pengalihan ke halaman web.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
