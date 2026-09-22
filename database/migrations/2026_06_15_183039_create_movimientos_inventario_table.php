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
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('inventario')->onDelete('cascade');
            $table->foreignId('tecnico_id')->nullable()->constrained('tecnicos')->onDelete('set null');
            $table->string('tipo'); // entrada, salida, consumo, devolucion
            $table->integer('cantidad');
            $table->unsignedBigInteger('referencia_orden_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
