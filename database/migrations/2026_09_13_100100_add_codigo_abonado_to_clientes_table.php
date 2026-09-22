<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El código de abonado (TCF004906, C009302, SG000808) es el identificador
     * que la operación usa a diario, por encima de la cédula.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('codigo_abonado')->nullable()->unique()->after('id');
            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['nombre']);
            $table->dropUnique(['codigo_abonado']);
            $table->dropColumn('codigo_abonado');
        });
    }
};
