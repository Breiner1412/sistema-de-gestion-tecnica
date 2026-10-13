<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borrar un registro padre ya no arrastra el historial que depende de él.
 *
 * Con ON DELETE CASCADE, borrar en duro un cliente se llevaba todos sus
 * casos; un usuario, sus informes firmados; un material, su kardex; un
 * técnico, sus visitas y evidencias. Casi todo usa borrado lógico y en el
 * día a día no pasaba, pero bastaba un forceDelete() o un DELETE a mano para
 * perder la auditoría. Con RESTRICT la base se niega.
 *
 * Solo en MySQL, como el resto de cambios de claves foráneas del proyecto:
 * SQLite, el motor de las pruebas, no las altera sin reconstruir la tabla.
 * Las relaciones a soportes (visitas, historial) siguen en cascada a
 * propósito: el importador limpia lo importado borrando el caso.
 */
return new class extends Migration
{
    /** tabla => [columna, tabla referida] */
    private const CLAVES = [
        ['soportes', 'cliente_id', 'clientes'],
        ['informes_mensuales', 'tecnico_id', 'users'],
        ['materiales_orden', 'material_id', 'inventario'],
        ['movimientos_inventario', 'material_id', 'inventario'],
        ['ordenes_trabajo', 'tecnico_id', 'tecnicos'],
        ['evidencias', 'tecnico_id', 'tecnicos'],
    ];

    public function up(): void
    {
        $this->cambiar('restrict');
    }

    public function down(): void
    {
        $this->cambiar('cascade');
    }

    private function cambiar(string $alBorrar): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::CLAVES as [$tabla, $columna, $referida]) {
            Schema::table($tabla, function (Blueprint $table) use ($columna, $referida, $alBorrar) {
                $table->dropForeign([$columna]);
                $table->foreign($columna)->references('id')->on($referida)->onDelete($alBorrar);
            });
        }
    }
};
