<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
     * Disco de las fotos y las firmas. Es privado: muestran la casa del
     * cliente y su firma, y no pueden quedar a un enlace de distancia de
     * cualquiera. Se sirven por una ruta que pregunta a la política de la orden.
     */
    public const DISCO = 'local';

    /**
     * URL para mostrarla.
     *
     * `archivo_url` guarda la ruta relativa dentro del disco, no una URL
     * completa: si mañana el proyecto se mueve de dominio o pasa a S3, lo
     * guardado sigue sirviendo.
     */
    public function url(): string
    {
        return route('evidencias.ver', $this);
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
