<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipoInstalado extends Model
{
    use SoftDeletes;

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