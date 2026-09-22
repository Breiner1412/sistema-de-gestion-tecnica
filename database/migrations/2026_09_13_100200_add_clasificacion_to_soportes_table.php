<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            // Consecutivo legible: SGT-2026-000001
            $table->string('numero_soporte')->nullable()->unique()->after('id');

            // Qué tipo de solicitud es. En el Excel esto era una hoja distinta por tipo.
            $table->string('tipo_solicitud')->default('soporte_remoto')->after('contrato_id');
            // soporte_remoto, sin_internet, cambio_plan, cambio_titular

            $table->string('servicio_afectado')->nullable()->after('tipo_solicitud');
            // internet, tv, internet_tv

            $table->foreignId('tipo_falla_id')->nullable()->after('servicio_afectado')
                ->constrained('tipos_falla')->nullOnDelete();

            $table->foreignId('diagnostico_id')->nullable()->after('tipo_falla_id')
                ->constrained('diagnosticos')->nullOnDelete();

            $table->text('observaciones_cierre')->nullable()->after('descripcion');

            $table->boolean('escalado_nivel_3')->default(false)->after('prioridad');
            $table->dateTime('fecha_escalamiento')->nullable()->after('escalado_nivel_3');
            $table->text('motivo_escalamiento')->nullable()->after('fecha_escalamiento');

            // tiempo_respuesta ya existía: creación -> asignación.
            // tiempo_resolucion: creación -> cierre. Son métricas distintas.
            $table->integer('tiempo_resolucion')->nullable()->after('tiempo_respuesta');
            $table->dateTime('fecha_cierre')->nullable()->after('tiempo_resolucion');

            // Índices para los listados y el dashboard.
            $table->index(['estado', 'created_at']);
            $table->index(['tipo_solicitud', 'created_at']);
            $table->index('ingeniero_asignado_id');
        });
    }

    public function down(): void
    {
        Schema::table('soportes', function (Blueprint $table) {
            $table->dropIndex(['ingeniero_asignado_id']);
            $table->dropIndex(['tipo_solicitud', 'created_at']);
            $table->dropIndex(['estado', 'created_at']);

            $table->dropConstrainedForeignId('diagnostico_id');
            $table->dropConstrainedForeignId('tipo_falla_id');

            $table->dropUnique(['numero_soporte']);
            $table->dropColumn([
                'numero_soporte',
                'tipo_solicitud',
                'servicio_afectado',
                'observaciones_cierre',
                'escalado_nivel_3',
                'fecha_escalamiento',
                'motivo_escalamiento',
                'tiempo_resolucion',
                'fecha_cierre',
            ]);
        });
    }
};
