<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evidencia extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'orden_id',
        'tecnico_id',
        'tipo',
        'archivo_url',
        'descripcion',
        'latitud',
        'longitud',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Tecnico::class);
    }
}