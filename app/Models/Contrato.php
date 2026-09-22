<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contrato extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'numero_contrato',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'valor_contrato',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'valor_contrato' => 'decimal:2',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function equipos()
    {
        return $this->hasMany(EquipoInstalado::class);
    }
}