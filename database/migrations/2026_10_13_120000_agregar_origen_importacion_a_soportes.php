<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de qué fila del histórico salió cada caso importado.
 *
 * Sin ella el importador no sabía qué había importado ya: correrlo dos veces
 * duplicaba todas las cifras, y la única salida (--fresh) borraba también los
 * casos reales creados en la aplicación. Con la marca, reimportar salta lo que
 * ya está y --fresh borra solo lo importado. Los casos creados a mano la
 * tienen en NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->string('origen_importacion', 64)->nullable()->unique()->after('numero_soporte');
        });
    }

    public function down(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->dropUnique(['origen_importacion']);
            $table->dropColumn('origen_importacion');
        });
    }
};
