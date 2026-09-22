<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoporteCambioPlan extends Model
{
    protected $table = 'soporte_cambio_plan';

    public const TIEMPOS_EJECUCION = [
        'inmediatamente' => 'Inmediatamente',
        'inicio_mes_siguiente' => 'Inicio del mes siguiente',
        'al_ejecutar_orden' => 'Tan pronto la orden se ejecute',
    ];

    protected $fillable = [
        'soporte_id',
        'plan_anterior_id',
        'plan_nuevo_id',
        'tiempo_ejecucion',
        'fecha_efectiva',
        'motivo',
    ];

    protected function casts(): array
    {
        return ['fecha_efectiva' => 'date'];
    }

    public function soporte()
    {
        return $this->belongsTo(Soporte::class);
    }

    public function planAnterior()
    {
        return $this->belongsTo(Plan::class, 'plan_anterior_id');
    }

    public function planNuevo()
    {
        return $this->belongsTo(Plan::class, 'plan_nuevo_id');
    }
}
