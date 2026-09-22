<?php

namespace App\Observers;

use App\Models\Inventario;
use App\Models\MaterialOrden;
use App\Models\MovimientoInventario;
use Illuminate\Validation\ValidationException;

class MaterialOrdenObserver
{
    /**
     * Se valida ANTES de insertar. Antes se hacía en created(), así que la fila
     * quedaba guardada aunque no hubiera stock.
     */
    public function creating(MaterialOrden $materialOrden): void
    {
        if ($materialOrden->cantidad_usada < 1) {
            throw ValidationException::withMessages([
                'cantidad_usada' => 'La cantidad usada debe ser al menos 1.',
            ]);
        }

        $material = Inventario::find($materialOrden->material_id);

        if (!$material) {
            throw ValidationException::withMessages([
                'material_id' => 'El material seleccionado no existe en el inventario.',
            ]);
        }

        if ($material->cantidad_total < $materialOrden->cantidad_usada) {
            throw ValidationException::withMessages([
                'cantidad_usada' => "No hay suficiente stock de '{$material->nombre}'. Disponible: {$material->cantidad_total}",
            ]);
        }
    }

    public function created(MaterialOrden $materialOrden): void
    {
        // Descuento atómico: la condición viaja en el UPDATE, así dos técnicos
        // consumiendo el mismo material a la vez no pueden dejar el stock negativo.
        $descontado = Inventario::whereKey($materialOrden->material_id)
            ->where('cantidad_total', '>=', $materialOrden->cantidad_usada)
            ->decrement('cantidad_total', $materialOrden->cantidad_usada);

        if ($descontado === 0) {
            // deleteQuietly: no debe disparar deleted() y devolver un stock que nunca se descontó.
            $materialOrden->deleteQuietly();

            throw ValidationException::withMessages([
                'cantidad_usada' => 'El stock se agotó mientras se registraba el consumo. Vuelve a intentarlo.',
            ]);
        }

        MovimientoInventario::create([
            'material_id' => $materialOrden->material_id,
            'tecnico_id' => $materialOrden->orden?->tecnico_id,
            'tipo' => 'consumo',
            'cantidad' => $materialOrden->cantidad_usada,
            'referencia_orden_id' => $materialOrden->orden_id,
        ]);
    }

    /**
     * Si se elimina el consumo, el material vuelve al inventario.
     */
    public function deleted(MaterialOrden $materialOrden): void
    {
        Inventario::whereKey($materialOrden->material_id)
            ->increment('cantidad_total', $materialOrden->cantidad_usada);

        MovimientoInventario::create([
            'material_id' => $materialOrden->material_id,
            'tecnico_id' => $materialOrden->orden?->tecnico_id,
            'tipo' => 'devolucion',
            'cantidad' => $materialOrden->cantidad_usada,
            'referencia_orden_id' => $materialOrden->orden_id,
        ]);
    }
}
