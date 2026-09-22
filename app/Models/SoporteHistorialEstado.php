<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoporteHistorialEstado extends Model
{
    protected $table = 'soporte_historial_estados';

    protected $fillable = [
        'soporte_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'motivo',
        'automatico',
    ];

    protected function casts(): array
    {
        return ['automatico' => 'boolean'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}