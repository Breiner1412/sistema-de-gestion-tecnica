<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialOrden extends Model
{
    protected $table = 'materiales_orden';

    protected $fillable = [
        'orden_id',
        'material_id',
        'cantidad_usada',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function material()
    {
        return $this->belongsTo(Inventario::class, 'material_id');
    }
}