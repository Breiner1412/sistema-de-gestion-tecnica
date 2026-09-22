<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle específico de los soportes con tipo_solicitud = cambio_plan.
     * En el Excel esto era la hoja "CAMBIO PLAN" (988 casos en el semestre).
     */
    public function up(): void
    {
        Schema::create('soporte_cambio_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soporte_id')->unique()->constrained('soportes')->cascadeOnDelete();
            $table->foreignId('plan_anterior_id')->nullable()->constrained('planes')->nullOnDelete();
            $table->foreignId('plan_nuevo_id')->nullable()->constrained('planes')->nullOnDelete();
            $table->string('tiempo_ejecucion')->default('inmediatamente');
            // inmediatamente, inicio_mes_siguiente, al_ejecutar_orden
            $table->date('fecha_efectiva')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soporte_cambio_plan');
    }
};
