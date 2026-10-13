<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primera barrera por rol en las rutas. La decisión fina (de quién es el caso,
 * la visita o el informe) la toman las políticas en cada acción.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Comparación estricta: un rol vacío o numérico no coincide por casualidad.
        if (!$request->user() || !in_array($request->user()->rol, $roles, true)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}