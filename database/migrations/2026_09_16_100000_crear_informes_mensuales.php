<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La operación hacía una vez al mes un informe de rendimiento por técnico,
     * con las cifras del mes y un bloque de observaciones firmado. Esto lo
     * reproduce: las métricas se congelan al publicar para que el documento no
     * cambie después, aunque los casos se sigan moviendo.
     */
    public function up(): void
    {
        Schema::create('informes_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tecnico_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');

            $table->text('observaciones')->nullable();
            $table->text('compromisos')->nullable();

            // Snapshot de las cifras al momento de publicar.
            $table->json('metricas')->nullable();

            $table->foreignId('generado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('publicado_at')->nullable();
            $table->dateTime('visto_at')->nullable();

            $table->timestamps();

            $table->unique(['tecnico_id', 'anio', 'mes']);
            $table->index(['anio', 'mes']);
        });

        // Las consultas de recurrencia siempre van por cliente y fecha.
        Schema::table('soportes', function (Blueprint $table) {
            $table->index(['cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->dropIndex(['cliente_id', 'created_at']);
        });

        Schema::dropIfExists('informes_mensuales');
    }
};
