<?php

namespace App\Services;

use App\Exceptions\TransicionInvalidaException;
use App\Models\Evidencia;
use App\Models\MaterialOrden;
use App\Models\OrdenTrabajo;
use App\Models\Soporte;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cerrar una visita, venga del escritorio o del celular.
 *
 * Existe porque cerrar una visita no es guardar un formulario: mueve el estado
 * de la orden, cierra o reabre el caso de soporte —con su máquina de estados y
 * su reloj de SLA—, descuenta material del inventario y guarda evidencias. Eso
 * estaba escrito dentro de la pantalla de escritorio, y el celular necesitaba
 * exactamente lo mismo. Dos copias de esta lógica habrían divergido en semanas.
 *
 * Lo propio del celular es la idempotencia. El teléfono puede quedarse sin
 * señal a mitad del envío y reintentar sin saber si el primero llegó: por eso
 * el identificador del cierre lo pone el teléfono, antes de enviar, y aquí se
 * reconoce el reenvío en vez de cerrar dos veces y descontar dos veces.
 */
class CierreDeVisita
{
    /** @var array<int, string> */
    private array $avisos = [];

    public function __construct(private readonly OrdenTrabajo $orden) {}

    public static function para(OrdenTrabajo $orden): self
    {
        return new self($orden);
    }

    /**
     * @param  array{
     *     estado: string,
     *     uuid?: string|null,
     *     diagnostico_id?: int|null,
     *     observaciones?: string|null,
     *     motivo?: string|null,
     *     usuario_id?: int|null,
     *     cerrada_en_terreno_at?: string|null,
     *     latitud?: float|null,
     *     longitud?: float|null,
     *     firma?: string|null,
     *     fotos?: array<int, array{contenido: string, descripcion?: string|null, tomada_at?: string|null}>,
     *     materiales?: array<int, array{material_id: int, cantidad: int}>,
     * }  $datos
     * @return array{orden: OrdenTrabajo, repetido: bool, avisos: array<int, string>}
     */
    public function registrar(array $datos): array
    {
        $uuid = $datos['uuid'] ?? null;

        // El mismo envío otra vez: se responde que sí y no se toca nada.
        if ($uuid && $this->orden->cierre_uuid === $uuid) {
            return $this->respuesta(repetido: true);
        }

        if (! $this->orden->estaAbierta()) {
            throw ValidationException::withMessages([
                'estado' => 'Esta visita ya fue cerrada por otra persona.',
            ]);
        }

        $archivos = [];

        try {
            $firma = isset($datos['firma']) ? $this->guardarFirma($datos['firma']) : null;

            if ($firma) {
                $archivos[] = $firma;
            }

            $fotos = $this->guardarFotos($datos['fotos'] ?? []);
            $archivos = array_merge($archivos, array_column($fotos, 'ruta'));

            DB::transaction(function () use ($datos, $uuid, $firma, $fotos) {
                $this->actualizarOrden($datos, $uuid, $firma);
                $this->registrarEvidencias($fotos, $datos);
                $this->registrarMateriales($datos['materiales'] ?? []);
                $this->moverElCaso($datos);
            });
        } catch (\Throwable $e) {
            // Sin esto, cada reintento fallido deja fotos huérfanas en disco.
            Storage::disk('public')->delete($archivos);

            throw $e;
        }

        return $this->respuesta(repetido: false);
    }

    /* ---------------------------------------------------------------
     | Partes
     * --------------------------------------------------------------- */

    private function actualizarOrden(array $datos, ?string $uuid, ?string $firma): void
    {
        $enTerreno = $this->momentoDelCierre($datos);

        $cambios = [
            'estado' => $datos['estado'],
            'cierre_uuid' => $uuid,
            'observaciones' => $datos['observaciones'] ?? $this->orden->observaciones,
            'motivo_no_realizada' => $datos['motivo'] ?? null,
            'cerrada_en_terreno_at' => $enTerreno,
            // La hora de fin es la del terreno, no la de la sincronización: si
            // cerró a las 10:05 sin señal, la visita terminó a las 10:05.
            'hora_fin' => $this->orden->hora_fin ?? $enTerreno,
        ];

        if (! $this->orden->hora_inicio) {
            $cambios['hora_inicio'] = $enTerreno;
        }

        if (isset($datos['latitud'], $datos['longitud'])) {
            $cambios['latitud_fin'] = $datos['latitud'];
            $cambios['longitud_fin'] = $datos['longitud'];
        }

        if ($firma) {
            $cambios['firma_path'] = $firma;
        }

        $this->orden->update($cambios);
    }

    /**
     * @param  array<int, array{ruta: string, descripcion: string|null, tomada_at: string|null}>  $fotos
     */
    private function registrarEvidencias(array $fotos, array $datos): void
    {
        foreach ($fotos as $foto) {
            Evidencia::create([
                'orden_id' => $this->orden->id,
                'tecnico_id' => $this->orden->tecnico_id,
                'tipo' => Evidencia::TIPO_FOTO,
                'archivo_url' => $foto['ruta'],
                'descripcion' => $foto['descripcion'],
                'latitud' => $datos['latitud'] ?? null,
                'longitud' => $datos['longitud'] ?? null,
                'tomada_at' => $foto['tomada_at'] ? Carbon::parse($foto['tomada_at']) : null,
            ]);
        }
    }

    /**
     * El material se registra uno por uno y a propósito: el observer descuenta
     * el inventario de forma atómica y avisa si no alcanza. Un cierre que llega
     * tres horas tarde puede encontrarse sin stock, y eso no es motivo para
     * rechazar la visita entera —ya está hecha—, sino para dejarlo anotado.
     *
     * @param  array<int, array{material_id: int, cantidad: int}>  $materiales
     */
    private function registrarMateriales(array $materiales): void
    {
        foreach ($materiales as $material) {
            try {
                MaterialOrden::create([
                    'orden_id' => $this->orden->id,
                    'material_id' => $material['material_id'],
                    'cantidad_usada' => $material['cantidad'],
                ]);
            } catch (ValidationException $e) {
                $this->avisos[] = collect($e->errors())->flatten()->first()
                    ?? 'No se pudo descontar un material del inventario.';
            }
        }
    }

    /**
     * La visita cierra o reabre el caso, siempre por la máquina de estados:
     * es lo que deja historial y lo que vuelve a poner en marcha el reloj.
     */
    private function moverElCaso(array $datos): void
    {
        $soporte = $this->orden->soporte;

        if (! $soporte || $soporte->estaCerrado()) {
            return;
        }

        try {
            if ($datos['estado'] === OrdenTrabajo::ESTADO_COMPLETADO) {
                $soporte->cerrar(
                    (int) $datos['diagnostico_id'],
                    (string) ($datos['observaciones'] ?? ''),
                    $datos['usuario_id'] ?? null,
                );

                return;
            }

            $soporte->cambiarEstado(
                Soporte::ESTADO_EN_PROCESO,
                'Visita no realizada: '.($datos['motivo'] ?? 'sin motivo registrado'),
                $datos['usuario_id'] ?? null,
            );
        } catch (TransicionInvalidaException $e) {
            // La visita sí se cerró; el caso quedó donde estaba. Se avisa en vez
            // de perder el cierre que el técnico ya hizo en terreno.
            $this->avisos[] = $e->getMessage();
        }
    }

    /* ---------------------------------------------------------------
     | Archivos
     * --------------------------------------------------------------- */

    private function guardarFirma(?string $contenido): ?string
    {
        $binario = $this->decodificar($contenido);

        if ($binario === null) {
            return null;
        }

        $ruta = 'firmas/'.Str::uuid().'.png';
        Storage::disk('public')->put($ruta, $binario);

        return $ruta;
    }

    /**
     * @param  array<int, array{contenido: string, descripcion?: string|null, tomada_at?: string|null}>  $fotos
     * @return array<int, array{ruta: string, descripcion: string|null, tomada_at: string|null}>
     */
    private function guardarFotos(array $fotos): array
    {
        $guardadas = [];

        foreach ($fotos as $foto) {
            $binario = $this->decodificar($foto['contenido'] ?? null);

            if ($binario === null) {
                continue;
            }

            $ruta = 'evidencias/'.$this->orden->id.'/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($ruta, $binario);

            $guardadas[] = [
                'ruta' => $ruta,
                'descripcion' => $foto['descripcion'] ?? null,
                'tomada_at' => $foto['tomada_at'] ?? null,
            ];
        }

        return $guardadas;
    }

    /** Acepta "data:image/jpeg;base64,AAA..." o el base64 pelado. */
    private function decodificar(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $base64 = str_contains($valor, ',') ? Str::after($valor, ',') : $valor;

        $binario = base64_decode($base64, true);

        return $binario !== false && $binario !== '' ? $binario : null;
    }

    /* ---------------------------------------------------------------
     | Auxiliares
     * --------------------------------------------------------------- */

    /**
     * La hora que manda es la del terreno, pero solo si es creíble: un reloj
     * de celular mal puesto no puede fechar una visita en el año que viene.
     */
    private function momentoDelCierre(array $datos): Carbon
    {
        $declarada = ($datos['cerrada_en_terreno_at'] ?? null)
            ? Carbon::parse($datos['cerrada_en_terreno_at'])
            : null;

        if (! $declarada || $declarada->isFuture() || $declarada->lessThan($this->orden->created_at)) {
            return now();
        }

        return $declarada;
    }

    /** @return array{orden: OrdenTrabajo, repetido: bool, avisos: array<int, string>} */
    private function respuesta(bool $repetido): array
    {
        return [
            'orden' => $this->orden->refresh(),
            'repetido' => $repetido,
            'avisos' => $this->avisos,
        ];
    }
}
