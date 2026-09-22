<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('soportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->onDelete('set null');
            $table->foreignId('usuario_registra_id')->constrained('users');
            $table->text('descripcion');
            $table->string('estado')->default('pendiente');
            // pendiente, en_proceso, enviado_tecnico, solucionado, cancelado
            $table->string('prioridad')->default('media');
            // baja, media, alta
            $table->foreignId('ingeniero_asignado_id')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('fecha_asignacion')->nullable();
            $table->integer('tiempo_respuesta')->nullable(); // en minutos
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soportes');
    }
};
