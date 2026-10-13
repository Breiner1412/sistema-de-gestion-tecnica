<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventario extends Model
{
    use SoftDeletes;

    protected $table = 'inventario';

    protected $fillable = [
        'nombre',
        'tipo',
        'cantidad_total',
        'cantidad_minima',
        'precio_unitario',
    ];

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class, 'material_id');
    }
}