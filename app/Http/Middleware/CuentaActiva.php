<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Desactivar una cuenta tiene que sacar a quien ya estaba dentro. Sin esto, la
 * sesión abierta (o la cookie de "recordarme") seguía funcionando: desactivar
 * una cuenta comprometida no servía de nada hasta que la sesión caducara.
 */
class CuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->estado === 'activo') {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $mensaje = 'Tu cuenta está desactivada. Habla con el administrador.';

        return $request->expectsJson()
            ? response()->json(['message' => $mensaje], 401)
            : redirect()->route('login')->with('status', $mensaje);
    }
}
