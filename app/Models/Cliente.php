<?php

namespace App\Models;

use App\Models\Concerns\DetectaRecurrencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use DetectaRecurrencia, SoftDeletes;

    protected $fillable = [
        'codigo_abonado',
        'cedula',
        'nombre',
        'telefono',
        'correo',
        'direccion',
        'latitud',
        'longitud',
        'estado',
    ];

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('nombre', 'like', "%{$termino}%")
                ->orWhere('cedula', 'like', "%{$termino}%")
                ->orWhere('codigo_abonado', 'like', "%{$termino}%");
        });
    }

    public function etiqueta(): string
    {
        return trim($this->nombre.' — '.($this->codigo_abonado ?: $this->cedula));
    }

    public function contratos()
    {
        return $this->hasMany(Contrato::class);
    }

    public function soportes()
    {
        return $this->hasMany(Soporte::class);
    }
}
