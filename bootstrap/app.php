<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ojo: definir esto REEMPLAZA el criterio por defecto de Laravel, que es
        // justamente $request->expectsJson(). Con solo 'api/*', un error de
        // validación en cualquier otra ruta salía como redirección HTML aunque
        // quien preguntara fuera fetch() esperando JSON —que es como habla el
        // celular con /campo—. Se conserva el forzado de api/* y se devuelve el
        // comportamiento normal para todo lo demás.
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
