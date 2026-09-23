<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipoInstalado extends Model
{
    use SoftDeletes;

    // Eloquent pluraliza solo la ultima palabra: de EquipoInstalado deduce
    // 'equipo_instalados', y la tabla es 'equipos_instalados'.
    protected $table = 'equipos_instalados';

    protected $fillable = [
        'contrato_id',
        'tipo',
        'modelo',
        'numero_serie',
        'fecha_instalacion',
        'estado',
    ];

    protected function casts(): array
    {
        return ['fecha_instalacion' => 'date'];
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }
}