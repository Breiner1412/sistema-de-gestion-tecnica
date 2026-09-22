<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoporteCambioTitular extends Model
{
    protected $table = 'soporte_cambio_titular';

    protected $fillable = [
        'soporte_id',
        'titular_anterior_id',
        'titular_nuevo_id',
        'titular_nuevo_cedula',
        'titular_nuevo_nombre',
        'titular_nuevo_telefono',
        'incluye_cambio_plan',
        'incluye_traslado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'incluye_cambio_plan' => 'boolean',
            'incluye_traslado' => 'boolean',
        ];
    }

    public function soporte()
    {
        return $this->belongsTo(Soporte::class);
    }

    public function titularAnterior()
    {
        return $this->belongsTo(Cliente::class, 'titular_anterior_id');
    }

    public function titularNuevo()
    {
        return $this->belongsTo(Cliente::class, 'titular_nuevo_id');
    }
}
