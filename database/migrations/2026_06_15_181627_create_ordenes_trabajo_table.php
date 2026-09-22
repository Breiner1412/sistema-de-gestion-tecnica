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
        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soporte_id')->constrained('soportes')->onDelete('cascade');
            $table->foreignId('tecnico_id')->constrained('tecnicos')->onDelete('cascade');
            $table->string('tipo'); // instalacion, mantenimiento, revision
            $table->string('estado')->default('pendiente'); // pendiente, en_progreso, completado
            $table->dateTime('hora_inicio')->nullable();
            $table->dateTime('hora_fin')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('latitud_inicio', 10, 7)->nullable();
            $table->decimal('longitud_inicio', 10, 7)->nullable();
            $table->decimal('latitud_fin', 10, 7)->nullable();
            $table->decimal('longitud_fin', 10, 7)->nullable();
            $table->longText('firma_cliente')->nullable(); // imagen en base64
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes_trabajo');
    }
};
