<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Evidencia extends Model
{
    use SoftDeletes;

    public const TIPO_FOTO = 'foto';
    public const TIPO_VIDEO = 'video';

    protected $fillable = [
        'orden_id',
        'tecnico_id',
        'tipo',
        'archivo_url',
        'descripcion',
        'latitud',
        'longitud',
        'tomada_at',
    ];

    protected function casts(): array
    {
        return [
            'tomada_at' => 'datetime',
        ];
    }

    /**
     * URL para mostrarla.
     *
     * `archivo_url` guarda la ruta relativa dentro del disco público, no una
     * URL completa: si mañana el proyecto se mueve de dominio o pasa a S3, lo
     * guardado sigue sirviendo.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->archivo_url);
    }

    public function orden()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Tecnico::class);
    }
}
