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

        // En el grupo web va también en /livewire/update y en la cola del
        // celular: una cuenta desactivada se queda fuera en la siguiente petición.
        $middleware->appendToGroup('web', \App\Http\Middleware\CuentaActiva::class);

        // Las IPs salen de config/trustedproxy.php (TRUSTED_PROXIES), que el
        // middleware lee en cada petición. Aquí solo se fijan las cabeceras
        // que manda Caddy; el resto (ELB, prefijo) no se acepta de nadie.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);
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
