<?php

namespace App\Models\Concerns;

use App\Exceptions\TransicionInvalidaException;
use App\Models\SoporteHistorialEstado;
use Illuminate\Support\Facades\DB;

/**
 * Máquina de estados del caso: qué movimientos son válidos (TRANSICIONES, en
 * Soporte), cómo se aplican bajo bloqueo de fila y el rastro que dejan en el
 * historial. Es el único camino para mover un caso.
 */
trait MaquinaDeEstados
{
    public function puedeTransicionarA(string $estado): bool
    {
        return in_array($estado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    /** @return array<int, string> */
    public function transicionesDisponibles(): array
    {
        return self::TRANSICIONES[$this->estado] ?? [];
    }

    /**
     * Único camino para mover un caso. Valida la transición, ajusta el reloj,
     * calcula métricas y deja rastro en el historial.
     *
     * @throws TransicionInvalidaException
     */
    public function cambiarEstado(string $nuevoEstado, ?string $motivo = null, ?int $usuarioId = null): void
    {
        $usuarioId ??= auth()->id();

        DB::transaction(function () use ($nuevoEstado, $motivo, $usuarioId) {
            // Dos personas (o una persona y el reintento automático) pueden
            // mover el mismo caso a la vez. Se relee la fila bloqueada y la
            // transición se valida contra lo que hay ahora, no contra lo que
            // había cuando se abrió la pantalla.
            $this->releerBloqueado();

            $anterior = $this->estado;

            if ($anterior === $nuevoEstado) {
                return;
            }

            if (!$this->puedeTransicionarA($nuevoEstado)) {
                throw TransicionInvalidaException::para($anterior, $nuevoEstado);
            }

            $cambios = ['estado' => $nuevoEstado];

            if ($nuevoEstado === self::ESTADO_CANCELADO) {
                $cambios['motivo_cancelacion'] = $motivo;
            }

            if (in_array($nuevoEstado, self::ESTADOS_FINALES, true) && !$this->fecha_cierre) {
                $cambios['fecha_cierre'] = now();
                $cambios['tiempo_resolucion'] = $this->calendario()->minutosEntre($this->created_at, now());
            }

            $reabre = in_array($anterior, self::ESTADOS_FINALES, true) && $nuevoEstado === self::ESTADO_EN_PROCESO;

            // Reabrir un caso cerrado devuelve el reloj a cero desde ahora.
            if ($reabre) {
                $cambios['fecha_cierre'] = null;
                $cambios['tiempo_resolucion'] = null;
            }

            $this->fill($cambios);

            if ($reabre) {
                $this->iniciarSla(now());
            }

            // El reloj del nivel 2 solo corre mientras el caso es suyo.
            if (in_array($nuevoEstado, self::ESTADOS_QUE_PAUSAN_SLA, true)) {
                $this->pausarSla();
            } elseif (in_array($anterior, self::ESTADOS_QUE_PAUSAN_SLA, true)) {
                $this->reanudarSla();
            }

            $this->save();

            $this->registrarEnHistorial($anterior, $nuevoEstado, $motivo, $usuarioId);
        });
    }

    /**
     * Trae la fila con lockForUpdate (debe llamarse dentro de una transacción)
     * y conserva encima lo que este modelo tenga sin guardar.
     */
    private function releerBloqueado(): void
    {
        if (! $this->exists) {
            return;
        }

        $fila = static::withTrashed()->whereKey($this->getKey())->lockForUpdate()->first();

        if (! $fila) {
            return;
        }

        $sinGuardar = $this->getDirty();

        // getDirty() devuelve valores en bruto: se reponen tal cual, sin casts.
        $this->setRawAttributes($fila->getAttributes(), true);
        $this->attributes = array_merge($this->attributes, $sinGuardar);
    }

    /**
     * Sin usuario ni sesión detrás, la transición la hizo el sistema
     * (por ejemplo el job de reintentos) y así queda marcada en el historial.
     */
    private function registrarEnHistorial(?string $anterior, string $nuevo, ?string $motivo, ?int $usuarioId): void
    {
        $autor = $usuarioId ?? auth()->id();

        SoporteHistorialEstado::create([
            'soporte_id' => $this->id,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'usuario_id' => $autor,
            'motivo' => $motivo,
            'automatico' => $autor === null,
        ]);
    }
}
