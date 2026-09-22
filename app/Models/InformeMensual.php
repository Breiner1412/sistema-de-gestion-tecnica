<?php

namespace App\Models;

use App\Support\MetricasTecnico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Informe de rendimiento mensual de un técnico de soporte.
 *
 * La operación lo hacía en papel una vez al mes: las cifras del período, un
 * bloque de observaciones y una firma. Al publicarlo se congelan las métricas,
 * porque un documento firmado no puede cambiar de contenido después.
 */
class InformeMensual extends Model
{
    protected $table = 'informes_mensuales';

    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    protected $fillable = [
        'tecnico_id',
        'anio',
        'mes',
        'observaciones',
        'compromisos',
        'metricas',
        'generado_por_id',
        'publicado_at',
        'visto_at',
    ];

    protected function casts(): array
    {
        return [
            'metricas' => 'array',
            'publicado_at' => 'datetime',
            'visto_at' => 'datetime',
        ];
    }

    public function estaPublicado(): bool
    {
        return (bool) $this->publicado_at;
    }

    public function periodo(): string
    {
        return (self::MESES[$this->mes] ?? $this->mes).' de '.$this->anio;
    }

    /**
     * Las cifras congeladas si ya se publicó; si no, se calculan al vuelo para
     * que el borrador siempre muestre el estado actual.
     */
    public function cifras(): array
    {
        if ($this->estaPublicado() && $this->metricas) {
            return $this->metricas;
        }

        return MetricasTecnico::para($this->tecnico, $this->anio, $this->mes)->calcular();
    }

    public function publicar(?int $usuarioId = null): void
    {
        $this->update([
            'metricas' => MetricasTecnico::para($this->tecnico, $this->anio, $this->mes)->calcular(),
            'publicado_at' => now(),
            'generado_por_id' => $usuarioId ?? auth()->id() ?? $this->generado_por_id,
        ]);
    }

    public function scopeDelPeriodo(Builder $query, int $anio, ?int $mes = null): Builder
    {
        return $query->where('anio', $anio)->when($mes, fn($q) => $q->where('mes', $mes));
    }

    public function tecnico()
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function generadoPor()
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }
}
