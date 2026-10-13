<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La base también cuida el inventario, no solo el código.
 *
 * El descuento ya es atómico (la condición viaja en el UPDATE), pero
 * cualquier camino que se salte eso —un UPDATE a mano, un comando nuevo—
 * podía dejar el stock en negativo o registrar un consumo de cero. MySQL
 * aplica CHECK desde la 8.0.16; SQLite, el de las pruebas, no los altera
 * sobre una tabla existente, así que solo se crean en MySQL.
 */
return new class extends Migration
{
    private const RESTRICCIONES = [
        ['inventario', 'inventario_stock_no_negativo', 'cantidad_total >= 0'],
        ['inventario', 'inventario_minimo_no_negativo', 'cantidad_minima >= 0'],
        ['materiales_orden', 'materiales_orden_cantidad_positiva', 'cantidad_usada > 0'],
        ['movimientos_inventario', 'movimientos_inventario_cantidad_positiva', 'cantidad > 0'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::RESTRICCIONES as [$tabla, $nombre, $condicion]) {
            DB::statement("ALTER TABLE `{$tabla}` ADD CONSTRAINT `{$nombre}` CHECK ({$condicion})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::RESTRICCIONES as [$tabla, $nombre]) {
            DB::statement("ALTER TABLE `{$tabla}` DROP CHECK `{$nombre}`");
        }
    }
};
