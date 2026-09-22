<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos que en el Excel vivían en la hoja CONFIGURACIÓN.
     */
    public function up(): void
    {
        // Diagnóstico técnico del caso: falla de fibra, wifi desactivado, MTU 1500, etc.
        Schema::create('diagnosticos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Falla tal como la reporta el cliente: lentitud, sin señal de TV, canales faltantes...
        Schema::create('tipos_falla', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('servicio')->default('internet'); // internet, tv, ambos
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Planes comerciales, para cambio de plan.
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->unsignedInteger('velocidad_mb')->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->string('categoria')->default('hogar'); // hogar, corporativo, larga_reserva, vip
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
        Schema::dropIfExists('tipos_falla');
        Schema::dropIfExists('diagnosticos');
    }
};
