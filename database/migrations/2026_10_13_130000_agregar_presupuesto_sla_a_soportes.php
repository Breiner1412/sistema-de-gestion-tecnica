<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minutos hábiles que tenía el caso entre que arrancó su reloj y su
 * vencimiento. El semáforo mide el porcentaje consumido contra esto.
 *
 * Antes se calculaba como días × horas de jornada, pero el vencimiento cae
 * al cierre del quinto día hábil: un caso que entra a primera hora tiene seis
 * jornadas, no cinco. El porcentaje no cuadraba con el plazo y el aviso de
 * riesgo salía tarde. Se guarda al arrancar el reloj porque después la fecha
 * de vencimiento se mueve con las pausas y ya no sirve para deducirlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->unsignedInteger('sla_presupuesto_minutos')->nullable()->after('sla_minutos_restantes');
        });
    }

    public function down(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->dropColumn('sla_presupuesto_minutos');
        });
    }
};
