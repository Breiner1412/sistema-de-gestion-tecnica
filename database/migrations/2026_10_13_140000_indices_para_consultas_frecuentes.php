<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las consultas que corren en cada pantalla.
 *
 *  - (tecnico_soporte_id, created_at): el informe mensual y el panel filtran
 *    por técnico y rango de fechas unas ocho veces por informe.
 *  - (estado, sla_vence_at): la bandeja y el panel buscan los vencidos en
 *    cada render; el índice (criticidad, sla_vence_at) no servía porque no se
 *    filtra por criticidad.
 *
 * Y en MySQL se quita un índice sobrante: al renombrar ingeniero_asignado_id
 * a tecnico_soporte_id quedó vivo el índice de la FK vieja junto al nuevo.
 */
return new class extends Migration
{
    private const SOBRANTE = 'soportes_ingeniero_asignado_id_foreign';

    public function up(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->index(['tecnico_soporte_id', 'created_at']);
            $table->index(['estado', 'sla_vence_at']);
        });

        if ($this->existeIndice(self::SOBRANTE)) {
            Schema::table('soportes', fn (Blueprint $table) => $table->dropIndex(self::SOBRANTE));
        }
    }

    public function down(): void
    {
        // La migración 2026_09_14 cuenta con este índice en su down().
        if (DB::getDriverName() === 'mysql' && ! $this->existeIndice(self::SOBRANTE)) {
            Schema::table('soportes', fn (Blueprint $table) => $table->index('tecnico_soporte_id', self::SOBRANTE));
        }

        Schema::table('soportes', function (Blueprint $table) {
            $table->dropIndex(['estado', 'sla_vence_at']);
            $table->dropIndex(['tecnico_soporte_id', 'created_at']);
        });
    }

    private function existeIndice(string $nombre): bool
    {
        return DB::getDriverName() === 'mysql'
            && collect(Schema::getIndexes('soportes'))->contains(fn ($indice) => $indice['name'] === $nombre);
    }
};
