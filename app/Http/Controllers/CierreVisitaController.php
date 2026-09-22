<?php

namespace App\Http\Controllers;

use App\Http\Requests\CerrarVisitaRequest;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Services\CierreDeVisita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La puerta por la que entra el celular.
 *
 * Es un controlador y no un componente Livewire a propósito: lo que llega aquí
 * puede venir de una cola guardada en el teléfono horas antes, reenviada por
 * JavaScript sin que haya nadie mirando la pantalla. Livewire necesita una
 * sesión de componente viva; esto solo necesita la sesión del usuario.
 */
class CierreVisitaController extends Controller
{
    /**
     * Marca el arranque de la visita, con la ubicación desde donde se marcó.
     *
     * Si el técnico estaba sin señal al llegar, esto nunca sale y el cierre se
     * encarga de poner la hora de inicio. Por eso aquí no se valida casi nada:
     * es información útil, no un requisito.
     */
    public function iniciar(Request $request, OrdenTrabajo $orden): JsonResponse
    {
        $this->autorizar($request, $orden);

        $datos = $request->validate([
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($orden->estaAbierta() && ! $orden->hora_inicio) {
            $orden->update([
                'estado' => OrdenTrabajo::ESTADO_EN_PROGRESO,
                'hora_inicio' => now(),
                'latitud_inicio' => $datos['latitud'] ?? null,
                'longitud_inicio' => $datos['longitud'] ?? null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'estado' => $orden->refresh()->estado,
        ]);
    }

    /**
     * Cierra la visita. Responde 200 también al reenvío del mismo cierre, para
     * que la cola del teléfono pueda borrar el pendiente sin quedarse dando
     * vueltas sobre algo que ya se guardó.
     */
    public function cerrar(CerrarVisitaRequest $request, OrdenTrabajo $orden): JsonResponse
    {
        $resultado = CierreDeVisita::para($orden)->registrar([
            ...$request->validated(),
            'usuario_id' => $request->user()->id,
        ]);

        return response()->json([
            'ok' => true,
            'repetido' => $resultado['repetido'],
            'avisos' => $resultado['avisos'],
            'orden' => [
                'id' => $resultado['orden']->id,
                'estado' => $resultado['orden']->estado,
                'etiqueta' => $resultado['orden']->etiquetaEstado(),
            ],
        ]);
    }

    private function autorizar(Request $request, OrdenTrabajo $orden): void
    {
        $usuario = $request->user();

        abort_unless(
            $usuario->tieneRol(...User::ROLES_GESTION)
                || $usuario->tecnico?->id === $orden->tecnico_id,
            403,
        );
    }
}
