<?php

namespace App\Models;

use App\Exceptions\TransicionInvalidaException;
use App\Models\Concerns\GestionaSla;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Un caso de soporte.
 *
 * Solo llega aquí lo que el primer nivel (caja, call center o WhatsApp) no
 * pudo resolver en el momento. De ahí en adelante lo trabaja un técnico de
 * soporte en remoto, y si el caso lo supera sale por una de dos puertas:
 * ingeniería de redes (nivel 3) o una visita en terreno.
 */
class Soporte extends Model
{
    use GestionaSla, SoftDeletes;

    /* ---------------------------------------------------------------
     | Estados
     * --------------------------------------------------------------- */

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_EN_PROCESO = 'en_proceso';
    public const ESTADO_SEGUIMIENTO = 'seguimiento';
    public const ESTADO_SIN_CONTACTO = 'sin_contacto';
    public const ESTADO_ENVIADO_TECNICO = 'enviado_tecnico';
    public const ESTADO_ESCALADO_N3 = 'escalado_n3';
    public const ESTADO_SOLUCIONADO = 'solucionado';
    public const ESTADO_CANCELADO = 'cancelado';
    public const ESTADO_CERRADO_SIN_CONTACTO = 'cerrado_sin_contacto';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_EN_PROCESO => 'En proceso',
        self::ESTADO_SEGUIMIENTO => 'En seguimiento',
        self::ESTADO_SIN_CONTACTO => 'Sin contacto',
        self::ESTADO_ENVIADO_TECNICO => 'En visita programada',
        self::ESTADO_ESCALADO_N3 => 'En ingeniería de redes',
        self::ESTADO_SOLUCIONADO => 'Solucionado',
        self::ESTADO_CANCELADO => 'Cancelado',
        self::ESTADO_CERRADO_SIN_CONTACTO => 'Cerrado sin contacto',
    ];

    public const ESTADOS_FINALES = [
        self::ESTADO_SOLUCIONADO,
        self::ESTADO_CANCELADO,
        self::ESTADO_CERRADO_SIN_CONTACTO,
    ];

    /** Estados en los que el caso no depende del técnico de soporte: el reloj se detiene. */
    public const ESTADOS_QUE_PAUSAN_SLA = [
        self::ESTADO_SIN_CONTACTO,
        self::ESTADO_ENVIADO_TECNICO,
        self::ESTADO_ESCALADO_N3,
    ];

    /**
     * Máquina de estados. Vive en el modelo, no en el formulario: cualquier
     * pantalla, comando o job que mueva un caso pasa por las mismas reglas.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSICIONES = [
        self::ESTADO_PENDIENTE => [
            self::ESTADO_EN_PROCESO,
            self::ESTADO_SIN_CONTACTO,
            self::ESTADO_CANCELADO,
        ],
        self::ESTADO_EN_PROCESO => [
            self::ESTADO_SEGUIMIENTO,
            self::ESTADO_SIN_CONTACTO,
            self::ESTADO_ENVIADO_TECNICO,
            self::ESTADO_ESCALADO_N3,
            self::ESTADO_SOLUCIONADO,
            self::ESTADO_CANCELADO,
        ],
        self::ESTADO_SEGUIMIENTO => [
            self::ESTADO_EN_PROCESO,
            self::ESTADO_SIN_CONTACTO,
            self::ESTADO_ENVIADO_TECNICO,
            self::ESTADO_ESCALADO_N3,
            self::ESTADO_SOLUCIONADO,
            self::ESTADO_CANCELADO,
        ],
        self::ESTADO_SIN_CONTACTO => [
            self::ESTADO_EN_PROCESO,
            self::ESTADO_SOLUCIONADO,
            self::ESTADO_CERRADO_SIN_CONTACTO,
            self::ESTADO_CANCELADO,
        ],
        self::ESTADO_ENVIADO_TECNICO => [
            self::ESTADO_EN_PROCESO,
            self::ESTADO_SOLUCIONADO,
            self::ESTADO_CANCELADO,
        ],
        self::ESTADO_ESCALADO_N3 => [
            self::ESTADO_EN_PROCESO,
            self::ESTADO_ENVIADO_TECNICO,
            self::ESTADO_SOLUCIONADO,
            self::ESTADO_CANCELADO,
        ],
        // Reabrir un caso cerrado se permite, pero la pantalla lo restringe a admin.
        self::ESTADO_SOLUCIONADO => [self::ESTADO_EN_PROCESO],
        self::ESTADO_CERRADO_SIN_CONTACTO => [self::ESTADO_EN_PROCESO],
        self::ESTADO_CANCELADO => [],
    ];

    /* ---------------------------------------------------------------
     | Otros catálogos
     * --------------------------------------------------------------- */

    public const CANAL_CAJA = 'caja';
    public const CANAL_CALL_CENTER = 'call_center';
    public const CANAL_WHATSAPP = 'whatsapp';

    public const CANALES = [
        self::CANAL_CAJA => 'Caja (presencial)',
        self::CANAL_CALL_CENTER => 'Call center',
        self::CANAL_WHATSAPP => 'WhatsApp soporte',
    ];

    public const CRITICIDAD_INMEDIATA = 'inmediata';
    public const CRITICIDAD_NORMAL = 'normal';

    public const CRITICIDADES = [
        self::CRITICIDAD_INMEDIATA => 'Inmediata',
        self::CRITICIDAD_NORMAL => 'Normal',
    ];

    public const TIPO_SOPORTE_REMOTO = 'soporte_remoto';
    public const TIPO_SIN_INTERNET = 'sin_internet';
    public const TIPO_CAMBIO_PLAN = 'cambio_plan';
    public const TIPO_CAMBIO_TITULAR = 'cambio_titular';

    public const TIPOS_SOLICITUD = [
        self::TIPO_SOPORTE_REMOTO => 'Soporte remoto',
        self::TIPO_SIN_INTERNET => 'Sin internet',
        self::TIPO_CAMBIO_PLAN => 'Cambio de plan',
        self::TIPO_CAMBIO_TITULAR => 'Cambio de titular',
    ];

    public const SERVICIOS = [
        'internet' => 'Internet',
        'tv' => 'TV',
        'internet_tv' => 'Internet y TV',
    ];

    protected $fillable = [
        'numero_soporte',
        'cliente_id',
        'contrato_id',
        'tipo_solicitud',
        'canal_ingreso',
        'criticidad',
        'criticidad_manual',
        'motivo_criticidad',
        'servicio_afectado',
        'tipo_falla_id',
        'diagnostico_id',
        'usuario_registra_id',
        'descripcion',
        'observaciones_cierre',
        'estado',
        'escalado_nivel_3',
        'fecha_escalamiento',
        'motivo_escalamiento',
        'escalado_a_id',
        'fecha_respuesta_n3',
        'respuesta_n3',
        'tecnico_soporte_id',
        'fecha_asignacion',
        'sla_vence_at',
        'sla_pausado_at',
        'sla_minutos_restantes',
        'intentos_contacto',
        'proximo_intento_at',
        'tiempo_respuesta',
        'tiempo_resolucion',
        'fecha_cierre',
        'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'datetime',
            'fecha_escalamiento' => 'datetime',
            'fecha_respuesta_n3' => 'datetime',
            'fecha_cierre' => 'datetime',
            'sla_vence_at' => 'datetime',
            'sla_pausado_at' => 'datetime',
            'proximo_intento_at' => 'datetime',
            'escalado_nivel_3' => 'boolean',
            'criticidad_manual' => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------
     | Ciclo de vida
     * --------------------------------------------------------------- */

    protected static function booted(): void
    {
        static::creating(function (Soporte $soporte) {
            $soporte->numero_soporte ??= static::siguienteNumero();

            if (!$soporte->criticidad_manual) {
                $soporte->criticidad = $soporte->criticidadDerivada();
            }
        });

        static::created(function (Soporte $soporte) {
            $soporte->iniciarSla();
            $soporte->saveQuietly();
        });
    }

    public static function siguienteNumero(): string
    {
        $anio = now()->year;

        $ultimo = static::withTrashed()
            ->where('numero_soporte', 'like', "SGT-{$anio}-%")
            ->orderByDesc('numero_soporte')
            ->value('numero_soporte');

        $consecutivo = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return sprintf('SGT-%d-%06d', $anio, $consecutivo);
    }

    /**
     * La criticidad no la escoge el asesor: sale de si el servicio está caído.
     * Criterio de la operación: sin internet, sin TV o sin ambos es inmediato;
     * todo lo demás va a cinco días hábiles.
     */
    public function criticidadDerivada(): string
    {
        if ($this->tipo_solicitud === self::TIPO_SIN_INTERNET) {
            return self::CRITICIDAD_INMEDIATA;
        }

        if (in_array($this->tipo_solicitud, [self::TIPO_CAMBIO_PLAN, self::TIPO_CAMBIO_TITULAR], true)) {
            return self::CRITICIDAD_NORMAL;
        }

        $falla = $this->tipo_falla_id
            ? ($this->relationLoaded('tipoFalla') ? $this->tipoFalla : TipoFalla::find($this->tipo_falla_id))
            : null;

        return $falla?->critica
            ? self::CRITICIDAD_INMEDIATA
            : self::CRITICIDAD_NORMAL;
    }

    /** Sube la criticidad a mano dejando constancia de por qué. */
    public function elevarCriticidad(string $motivo, ?int $usuarioId = null): void
    {
        $this->update([
            'criticidad' => self::CRITICIDAD_INMEDIATA,
            'criticidad_manual' => true,
            'motivo_criticidad' => $motivo,
        ]);

        $this->iniciarSla();
        $this->save();

        $this->registrarEnHistorial($this->estado, $this->estado, "Criticidad elevada a inmediata: {$motivo}", $usuarioId);
    }

    /* ---------------------------------------------------------------
     | Transiciones
     * --------------------------------------------------------------- */

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
        $anterior = $this->estado;

        if ($anterior === $nuevoEstado) {
            return;
        }

        if (!$this->puedeTransicionarA($nuevoEstado)) {
            throw TransicionInvalidaException::para($anterior, $nuevoEstado);
        }

        $usuarioId ??= auth()->id();

        DB::transaction(function () use ($nuevoEstado, $anterior, $motivo, $usuarioId) {
            $cambios = ['estado' => $nuevoEstado];

            if ($nuevoEstado === self::ESTADO_CANCELADO) {
                $cambios['motivo_cancelacion'] = $motivo;
            }

            if (in_array($nuevoEstado, self::ESTADOS_FINALES, true) && !$this->fecha_cierre) {
                $cambios['fecha_cierre'] = now();
                $cambios['tiempo_resolucion'] = $this->calendario()->minutosEntre($this->created_at, now());
            }

            // Reabrir un caso cerrado devuelve el reloj a cero desde ahora.
            if (in_array($anterior, self::ESTADOS_FINALES, true) && $nuevoEstado === self::ESTADO_EN_PROCESO) {
                $cambios['fecha_cierre'] = null;
                $cambios['tiempo_resolucion'] = null;
            }

            $this->fill($cambios);

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

    /* ---------------------------------------------------------------
     | Acciones del flujo
     * --------------------------------------------------------------- */

    /** Asigna el caso a un técnico de soporte y congela el tiempo de primera respuesta. */
    public function asignarTecnicoSoporte(int $usuarioId, ?int $registradoPor = null): void
    {
        $cambios = ['tecnico_soporte_id' => $usuarioId];

        if (!$this->fecha_asignacion) {
            $cambios['fecha_asignacion'] = now();
            $cambios['tiempo_respuesta'] = $this->calendario()->minutosEntre($this->created_at, now());
        }

        $this->update($cambios);

        if ($this->puedeTransicionarA(self::ESTADO_EN_PROCESO)) {
            $this->cambiarEstado(self::ESTADO_EN_PROCESO, null, $registradoPor);
        }
    }

    /**
     * El cliente no contestó. Programa el reintento y, al agotarlos, cierra el
     * caso dejando constancia en vez de dejarlo abandonado, que es lo que
     * pasaba en el Excel con el 12% de los casos.
     */
    public function marcarSinContacto(?int $usuarioId = null): void
    {
        $intentos = (int) $this->intentos_contacto + 1;
        $maximo = (int) config('sla.sin_contacto.max_intentos', 3);

        $this->update([
            'intentos_contacto' => $intentos,
            'proximo_intento_at' => $this->calendario()
                ->sumarHoras(now(), (float) config('sla.sin_contacto.horas_entre_intentos', 4)),
        ]);

        if ($intentos >= $maximo) {
            $this->cambiarEstado(
                self::ESTADO_SIN_CONTACTO,
                "Intento {$intentos} de {$maximo} sin respuesta.",
                $usuarioId
            );

            $this->cambiarEstado(
                self::ESTADO_CERRADO_SIN_CONTACTO,
                "Cerrado tras {$intentos} intentos de contacto sin respuesta.",
                $usuarioId
            );

            return;
        }

        $this->cambiarEstado(
            self::ESTADO_SIN_CONTACTO,
            "Intento {$intentos} de {$maximo} sin respuesta.",
            $usuarioId
        );
    }

    /** El caso supera al nivel 2 y pasa a ingeniería de redes. */
    public function escalarANivel3(int $ingenieroId, string $motivo, ?int $usuarioId = null): void
    {
        $this->update([
            'escalado_nivel_3' => true,
            'escalado_a_id' => $ingenieroId,
            'fecha_escalamiento' => now(),
            'motivo_escalamiento' => $motivo,
        ]);

        $this->cambiarEstado(self::ESTADO_ESCALADO_N3, $motivo, $usuarioId);
    }

    /** Redes responde y el caso vuelve al nivel 2. */
    public function devolverDeNivel3(string $respuesta, ?int $usuarioId = null): void
    {
        $this->update([
            'fecha_respuesta_n3' => now(),
            'respuesta_n3' => $respuesta,
        ]);

        $this->cambiarEstado(self::ESTADO_EN_PROCESO, "Respuesta de redes: {$respuesta}", $usuarioId);
    }

    public function cerrar(int $diagnosticoId, string $observaciones, ?int $usuarioId = null): void
    {
        $this->update([
            'diagnostico_id' => $diagnosticoId,
            'observaciones_cierre' => $observaciones,
        ]);

        $this->cambiarEstado(self::ESTADO_SOLUCIONADO, null, $usuarioId);
    }

    /* ---------------------------------------------------------------
     | Consultas de conveniencia
     * --------------------------------------------------------------- */

    /**
     * Cuántas veces había reportado este mismo abonado en la ventana anterior
     * a este caso. Es lo que convierte un caso suelto en un patrón.
     */
    public function reportesPreviosDelAbonado(?int $dias = null): int
    {
        return $this->cliente?->reportesPrevios($this->created_at, $dias, $this->id) ?? 0;
    }

    public function esReincidente(?int $dias = null): bool
    {
        $minimo = (int) config('sla.recurrencia.minimo_en_ventana', 2);

        return $this->reportesPreviosDelAbonado($dias) >= $minimo - 1;
    }

    public function estaCerrado(): bool
    {
        return in_array($this->estado, self::ESTADOS_FINALES, true);
    }

    public function esInmediato(): bool
    {
        return $this->criticidad === self::CRITICIDAD_INMEDIATA;
    }

    public function tieneVisitaPendiente(): bool
    {
        return $this->ordenesTrabajo()
            ->whereIn('estado', [OrdenTrabajo::ESTADO_PENDIENTE, OrdenTrabajo::ESTADO_PROGRAMADA, OrdenTrabajo::ESTADO_EN_PROGRESO])
            ->exists();
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS_SOLICITUD[$this->tipo_solicitud] ?? $this->tipo_solicitud;
    }

    public function etiquetaCanal(): string
    {
        return self::CANALES[$this->canal_ingreso] ?? $this->canal_ingreso;
    }

    /* ---------------------------------------------------------------
     | Scopes
     * --------------------------------------------------------------- */

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('numero_soporte', 'like', "%{$termino}%")
                ->orWhere('descripcion', 'like', "%{$termino}%")
                ->orWhereHas('cliente', function (Builder $c) use ($termino) {
                    $c->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('cedula', 'like', "%{$termino}%")
                        ->orWhere('codigo_abonado', 'like', "%{$termino}%");
                });
        });
    }

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->whereNotIn('soportes.estado', self::ESTADOS_FINALES);
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query->abiertos()
            ->whereNull('sla_pausado_at')
            ->whereNotNull('sla_vence_at')
            ->where('sla_vence_at', '<', now());
    }

    public function scopeDe(Builder $query, ?int $usuarioId): Builder
    {
        return $usuarioId ? $query->where('tecnico_soporte_id', $usuarioId) : $query;
    }

    /* ---------------------------------------------------------------
     | Relaciones
     * --------------------------------------------------------------- */

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }

    public function tipoFalla()
    {
        return $this->belongsTo(TipoFalla::class, 'tipo_falla_id');
    }

    public function diagnostico()
    {
        return $this->belongsTo(Diagnostico::class);
    }

    public function usuarioRegistra()
    {
        return $this->belongsTo(User::class, 'usuario_registra_id');
    }

    public function tecnicoSoporte()
    {
        return $this->belongsTo(User::class, 'tecnico_soporte_id');
    }

    public function escaladoA()
    {
        return $this->belongsTo(User::class, 'escalado_a_id');
    }

    public function ordenesTrabajo()
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

    public function historialEstados()
    {
        return $this->hasMany(SoporteHistorialEstado::class)->latest();
    }

    public function cambioPlan()
    {
        return $this->hasOne(SoporteCambioPlan::class);
    }

    public function cambioTitular()
    {
        return $this->hasOne(SoporteCambioTitular::class);
    }
}
