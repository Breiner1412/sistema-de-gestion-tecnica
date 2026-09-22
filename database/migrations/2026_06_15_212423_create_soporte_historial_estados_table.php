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
        Schema::create('soporte_historial_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soporte_id')->constrained('soportes')->onDelete('cascade');
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->foreignId('usuario_id')->constrained('users');
            $table->text('motivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soporte_historial_estados');
    }
};
