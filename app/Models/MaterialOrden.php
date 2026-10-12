<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialOrden extends Model
{
    protected $table = 'materiales_orden';

    protected $fillable = [
        'orden_id',
        'material_id',
        'cantidad_usada',
    ];

    /**
     * Si el DELETE de verdad borró la fila. Dos peticiones que quitan el mismo
     * consumo a la vez disparan las dos el evento deleted(), pero solo una
     * borra algo: solo esa debe devolver el material al inventario.
     */
    public bool $borradoEfectivo = false;

    protected function performDeleteOnModel(): void
    {
        $this->borradoEfectivo = $this->setKeysForSaveQuery($this->newModelQuery())->delete() > 0;

        $this->exists = false;
    }

    public function orden()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function material()
    {
        return $this->belongsTo(Inventario::class, 'material_id');
    }
}
