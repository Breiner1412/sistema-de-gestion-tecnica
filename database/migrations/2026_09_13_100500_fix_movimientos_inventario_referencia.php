<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * referencia_orden_id era el único unsignedBigInteger suelto del esquema:
     * apuntaba a ordenes_trabajo pero sin llave foránea.
     */
    public function up(): void
    {
        // SQLite (que es lo que usan las pruebas) no admite agregar llaves
        // foráneas a una tabla que ya existe; el índice sí se crea igual.
        $soportaFk = DB::connection()->getDriverName() !== 'sqlite';

        Schema::table('movimientos_inventario', function (Blueprint $table) use ($soportaFk) {
            if ($soportaFk) {
                $table->foreign('referencia_orden_id')
                    ->references('id')->on('ordenes_trabajo')
                    ->nullOnDelete();
            }

            $table->index(['material_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $soportaFk = DB::connection()->getDriverName() !== 'sqlite';

        Schema::table('movimientos_inventario', function (Blueprint $table) use ($soportaFk) {
            $table->dropIndex(['material_id', 'created_at']);

            if ($soportaFk) {
                $table->dropForeign(['referencia_orden_id']);
            }
        });
    }
};
