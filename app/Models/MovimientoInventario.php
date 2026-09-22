<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'material_id',
        'tecnico_id',
        'tipo',
        'cantidad',
        'referencia_orden_id',
    ];

    public function material()
    {
        return $this->belongsTo(Inventario::class, 'material_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Tecnico::class);
    }
}