<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deja constancia de hasta dónde se avisó de cada caso.
 *
 * Sin esto el comando de alertas vuelve a escribir cada vez que corre, y una
 * bandeja que avisa lo mismo cada hora deja de leerse a la semana. Guardando
 * el último nivel notificado, cada caso avisa una vez al entrar en riesgo y
 * una vez al vencerse, y nada más.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->string('alerta_sla_nivel', 20)->nullable()->after('sla_minutos_restantes');
            $table->dateTime('alerta_sla_at')->nullable()->after('alerta_sla_nivel');
        });
    }

    public function down(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->dropColumn(['alerta_sla_nivel', 'alerta_sla_at']);
        });
    }
};
