<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle de los soportes con tipo_solicitud = cambio_titular ("cambio de TT").
     * En el Excel era la hoja "CAMBIO PROPIETARIO" (273 casos).
     */
    public function up(): void
    {
        Schema::create('soporte_cambio_titular', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soporte_id')->unique()->constrained('soportes')->cascadeOnDelete();

            // Titular que sale: normalmente ya existe como cliente.
            $table->foreignId('titular_anterior_id')->nullable()->constrained('clientes')->nullOnDelete();

            // Titular que entra: puede o no existir todavía en el maestro de clientes.
            $table->foreignId('titular_nuevo_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('titular_nuevo_cedula')->nullable();
            $table->string('titular_nuevo_nombre')->nullable();
            $table->string('titular_nuevo_telefono')->nullable();

            $table->boolean('incluye_cambio_plan')->default(false);
            $table->boolean('incluye_traslado')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soporte_cambio_titular');
    }
};
