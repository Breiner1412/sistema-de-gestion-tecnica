<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'fecha_programada',
        'franja',
        'hora_inicio',
        'hora_fin',
        'observaciones',
        'latitud_inicio',
        'longitud_inicio',
        'latitud_fin',
        'longitud_fin',
        'firma_cliente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'hora_inicio' => 'datetime',
            'hora_fin' => 'datetime',
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
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            self::ESTADO_PROGRAMADA,
            self::ESTADO_EN_PROGRESO,
        ], true);
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereIn('estado', [
            self::ESTADO_PENDIENTE,
            self::ESTADO_PROGRAMADA,
            self::ESTADO_EN_PROGRESO,
        ]);
    }

    public function scopeDelDia(Builder $query, $fecha = null): Builder
    {
        return $query->whereDate('fecha_programada', $fecha ?? today());
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
