<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class OrdenTrabajo extends Model
{
    use SoftDeletes;

    protected $table = 'ordenes_trabajo';

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_PROGRAMADA = 'programada';
    public const ESTADO_EN_PROGRESO = 'en_progreso';
    public const ESTADO_COMPLETADO = 'completado';
    public const ESTADO_NO_REALIZADA = 'no_realizada';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Por programar',
        self::ESTADO_PROGRAMADA => 'Programada',
        self::ESTADO_EN_PROGRESO => 'En progreso',
        self::ESTADO_COMPLETADO => 'Completada',
        self::ESTADO_NO_REALIZADA => 'No realizada',
    ];

    public const ESTADOS_FINALES = [
        self::ESTADO_COMPLETADO,
        self::ESTADO_NO_REALIZADA,
    ];

    public const FRANJAS = [
        'manana' => 'Mañana (7:00 - 12:00)',
        'tarde' => 'Tarde (12:00 - 18:00)',
    ];

    public const TIPOS = [
        'revision' => 'Revisión',
        'instalacion' => 'Instalación',
        'mantenimiento' => 'Mantenimiento',
    ];

    protected $fillable = [
        'soporte_id',
        'tecnico_id',
        'tipo',
        'estado',
        'cierre_uuid',
        'fecha_programada',
        'franja',
        'hora_inicio',
        'hora_fin',
        'cerrada_en_terreno_at',
        'observaciones',
        'motivo_no_realizada',
        'latitud_inicio',
        'longitud_inicio',
        'latitud_fin',
        'longitud_fin',
        'firma_path',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'hora_inicio' => 'datetime',
            'hora_fin' => 'datetime',
            'cerrada_en_terreno_at' => 'datetime',
        ];
    }

    /** ¿La visita se hizo el día para el que se programó? */
    public function cumplioProgramacion(): ?bool
    {
        if (!$this->fecha_programada || !$this->hora_fin) {
            return null;
        }

        return $this->hora_fin->isSameDay($this->fecha_programada);
    }

    public function estaAbierta(): bool
    {
        return !in_array($this->estado, self::ESTADOS_FINALES, true);
    }

    /**
     * Se cerró sin señal y llegó después.
     *
     * Media hora de margen: el reloj del celular y el del servidor nunca van
     * exactamente iguales, y una diferencia de segundos no es una sincronización
     * diferida, es ruido.
     */
    public function cerradaEnDiferido(): bool
    {
        return $this->cerrada_en_terreno_at
            && $this->hora_fin
            && $this->cerrada_en_terreno_at->diffInMinutes($this->hora_fin, absolute: true) > 30;
    }

    public function urlFirma(): ?string
    {
        return $this->firma_path ? Storage::disk('public')->url($this->firma_path) : null;
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function etiquetaFranja(): ?string
    {
        return $this->franja ? (self::FRANJAS[$this->franja] ?? $this->franja) : null;
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereNotIn('estado', self::ESTADOS_FINALES);
    }

    public function scopeDelDia(Builder $query, $fecha = null): Builder
    {
        return $query->whereDate('fecha_programada', $fecha ?? today());
    }

    /** Visitas de un técnico, recibiendo el usuario y no la ficha de técnico. */
    public function scopeDelTecnicoUsuario(Builder $query, ?User $usuario): Builder
    {
        return $query->where('tecnico_id', $usuario?->tecnico?->id ?? 0);
    }

    public function soporte()
    {
        return $this->belongsTo(Soporte::class);
    }

    public function tecnico()
    {
        return $this->belongsTo(Tecnico::class);
    }

    public function materialesUsados()
    {
        return $this->hasMany(MaterialOrden::class, 'orden_id');
    }

    public function evidencias()
    {
        return $this->hasMany(Evidencia::class, 'orden_id');
    }
}
