<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Guarda el tema que el usuario elige. El cambio ya se ve en la página (lo
 * aplica el navegador al instante); esto solo lo recuerda para la próxima vez.
 */
class PreferenciasController extends Controller
{
    public function tema(Request $request): Response
    {
        $datos = $request->validate(['tema' => ['required', Rule::in(User::TEMAS)]]);

        $request->user()->forceFill(['tema' => $datos['tema']])->save();

        return response()->noContent();
    }

    public function temaCampo(Request $request): Response
    {
        $datos = $request->validate(['tema' => ['required', Rule::in(User::TEMAS_CAMPO)]]);

        $request->user()->forceFill(['tema_campo' => $datos['tema']])->save();

        return response()->noContent();
    }
}
