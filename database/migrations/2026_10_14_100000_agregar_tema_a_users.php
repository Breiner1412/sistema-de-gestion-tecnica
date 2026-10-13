<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tema de la interfaz, guardado en la cuenta para que se vea igual en
 * cualquier equipo.
 *
 *  - tema: escritorio. claro, oscuro o sistema (lo que diga el equipo).
 *  - tema_campo: la pantalla de campo, que va aparte porque se usa al sol:
 *    claro por defecto y oscuro solo si el técnico lo pide para la noche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tema', 10)->default('sistema')->after('estado');
            $table->string('tema_campo', 10)->default('claro')->after('tema');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tema', 'tema_campo']);
        });
    }
};
