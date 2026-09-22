<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TipoFalla extends Model
{
    protected $table = 'tipos_falla';

    protected $fillable = ['nombre', 'servicio', 'critica', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'critica' => 'boolean',
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('nombre');
    }

    /** Fallas aplicables a un servicio: las propias del servicio más las de 'ambos'. */
    public function scopeParaServicio(Builder $query, ?string $servicio): Builder
    {
        if (blank($servicio)) {
            return $query;
        }

        $equivalencia = match ($servicio) {
            'internet' => ['internet'],
            'tv' => ['tv'],
            default => ['internet', 'tv'],
        };

        return $query->whereIn('servicio', [...$equivalencia, 'ambos']);
    }

    public function soportes()
    {
        return $this->hasMany(Soporte::class, 'tipo_falla_id');
    }
}
