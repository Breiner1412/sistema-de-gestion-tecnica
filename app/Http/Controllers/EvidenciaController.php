<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\OrdenTrabajo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las fotos y las firmas de las visitas, solo para quien puede ver la visita.
 *
 * Vivían en el disco público: cualquiera con el enlace veía la fachada de la
 * casa del cliente y su firma. Ahora están en el disco privado y cada archivo
 * pasa por la misma política que la pantalla de la visita.
 */
class EvidenciaController extends Controller
{
    public function foto(Evidencia $evidencia): StreamedResponse
    {
        Gate::authorize('view', $evidencia->orden);

        return $this->servir($evidencia->archivo_url);
    }

    public function firma(OrdenTrabajo $orden): StreamedResponse
    {
        Gate::authorize('view', $orden);

        abort_unless($orden->firma_path, 404);

        return $this->servir($orden->firma_path);
    }

    private function servir(string $ruta): StreamedResponse
    {
        $disco = Storage::disk(Evidencia::DISCO);

        abort_unless($disco->exists($ruta), 404);

        return $disco->response($ruta, null, [
            // Privado: ni Caddy ni un proxy intermedio la guardan para otro.
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
