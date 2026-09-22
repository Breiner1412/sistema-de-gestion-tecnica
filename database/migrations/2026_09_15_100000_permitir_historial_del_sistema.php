<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hay transiciones que no las hace una persona: el reintento automático de
     * los casos sin contacto, por ejemplo. El historial debe poder registrarlas
     * sin inventarse un usuario.
     */
    public function up(): void
    {
        // SQLite no admite soltar ni agregar llaves foráneas sobre una tabla
        // existente, pero sí reconstruirla al cambiar una columna.
        $soportaFk = DB::connection()->getDriverName() !== 'sqlite';

        if ($soportaFk) {
            Schema::table('soporte_historial_estados', function (Blueprint $table) {
                $table->dropForeign(['usuario_id']);
            });
        }

        Schema::table('soporte_historial_estados', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable()->change();
        });

        Schema::table('soporte_historial_estados', function (Blueprint $table) use ($soportaFk) {
            if ($soportaFk) {
                $table->foreign('usuario_id')->references('id')->on('users')->nullOnDelete();
            }

            $table->boolean('automatico')->default(false)->after('motivo');
            $table->index(['soporte_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $soportaFk = DB::connection()->getDriverName() !== 'sqlite';

        Schema::table('soporte_historial_estados', function (Blueprint $table) use ($soportaFk) {
            $table->dropIndex(['soporte_id', 'created_at']);
            $table->dropColumn('automatico');

            if ($soportaFk) {
                $table->dropForeign(['usuario_id']);
            }
        });

        Schema::table('soporte_historial_estados', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable(false)->change();
        });

        if ($soportaFk) {
            Schema::table('soporte_historial_estados', function (Blueprint $table) {
                $table->foreign('usuario_id')->references('id')->on('users');
            });
        }
    }
};
