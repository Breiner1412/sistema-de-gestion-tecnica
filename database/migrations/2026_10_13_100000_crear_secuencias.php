<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contadores con bloqueo de fila. El consecutivo de los casos se calculaba
 * con un max()+1 sin bloqueo: dos altas simultáneas sacaban el mismo número y
 * una terminaba en error 500 contra el índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secuencias', function (Blueprint $table) {
            $table->string('clave', 50)->primary();
            $table->unsignedBigInteger('valor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secuencias');
    }
};
