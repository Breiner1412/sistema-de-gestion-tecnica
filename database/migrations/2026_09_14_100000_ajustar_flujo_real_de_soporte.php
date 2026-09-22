<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajusta el modelo al flujo real de la operación:
     *
     *  - El caso entra por caja, call center o WhatsApp, y solo se registra
     *    cuando el primer nivel no lo pudo resolver.
     *  - Quien atiende en remoto es el TÉCNICO DE SOPORTE (nivel 2). Los
     *    ingenieros son los de redes, que son el nivel 3.
     *  - La criticidad no se escoge: se deriva de si el servicio está caído.
     *  - El reloj del nivel 2 se pausa cuando el caso pasa a redes o a visita.
     */
    public function up(): void
    {
        // Un tipo de falla marcado como crítico implica servicio caído.
        Schema::table('tipos_falla', function (Blueprint $table) {
            $table->boolean('critica')->default(false)->after('servicio');
        });

        Schema::table('soportes', function (Blueprint $table) {
            $table->string('canal_ingreso')->default('call_center')->after('tipo_solicitud');
            // caja, call_center, whatsapp

            $table->string('criticidad')->default('normal')->after('canal_ingreso');
            // inmediata, normal

            $table->boolean('criticidad_manual')->default(false)->after('criticidad');
            $table->text('motivo_criticidad')->nullable()->after('criticidad_manual');

            // Reloj de SLA del nivel 2.
            $table->dateTime('sla_vence_at')->nullable()->after('fecha_asignacion');
            $table->dateTime('sla_pausado_at')->nullable()->after('sla_vence_at');
            $table->integer('sla_minutos_restantes')->nullable()->after('sla_pausado_at');

            // Reintentos cuando el cliente no contesta.
            $table->unsignedTinyInteger('intentos_contacto')->default(0)->after('sla_minutos_restantes');
            $table->dateTime('proximo_intento_at')->nullable()->after('intentos_contacto');

            // Nivel 3: escalar deja un responsable, no solo una bandera.
            $table->foreignId('escalado_a_id')->nullable()->after('motivo_escalamiento')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_respuesta_n3')->nullable()->after('escalado_a_id');
            $table->text('respuesta_n3')->nullable()->after('fecha_respuesta_n3');

            $table->dropColumn('prioridad');
        });

        // SQLite no admite soltar ni agregar llaves foráneas sobre una tabla
        // existente; en ese motor solo se renombra la columna.
        $soportaFk = DB::connection()->getDriverName() !== 'sqlite';

        // El "ingeniero asignado" era en realidad el técnico de soporte de nivel 2.
        Schema::table('soportes', function (Blueprint $table) use ($soportaFk) {
            if ($soportaFk) {
                $table->dropForeign(['ingeniero_asignado_id']);
            }

            $table->dropIndex(['ingeniero_asignado_id']);
            $table->renameColumn('ingeniero_asignado_id', 'tecnico_soporte_id');
        });

        Schema::table('soportes', function (Blueprint $table) use ($soportaFk) {
            if ($soportaFk) {
                $table->foreign('tecnico_soporte_id')->references('id')->on('users')->nullOnDelete();
            }

            $table->index('tecnico_soporte_id');
            $table->index(['criticidad', 'sla_vence_at']);
            $table->index('canal_ingreso');
        });

        // Las visitas se programan; el caso no puede cerrarse antes de que ocurran.
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->date('fecha_programada')->nullable()->after('estado');
            $table->string('franja')->nullable()->after('fecha_programada'); // manana, tarde
            $table->index(['estado', 'fecha_programada']);
        });

        // Renombrado de roles existentes.
        DB::table('users')->where('rol', 'ingeniero')->update(['rol' => 'tecnico_soporte']);
        DB::table('users')->where('rol', 'tecnico')->update(['rol' => 'tecnico_campo']);

        // Fallas que significan servicio caído.
        DB::table('tipos_falla')
            ->whereIn('nombre', ['SIN SEÑAL DE TV', 'FALLAS CON LA TV'])
            ->update(['critica' => true]);
    }

    public function down(): void
    {
        DB::table('users')->where('rol', 'tecnico_soporte')->update(['rol' => 'ingeniero']);
        DB::table('users')->where('rol', 'tecnico_campo')->update(['rol' => 'tecnico']);

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropIndex(['estado', 'fecha_programada']);
            $table->dropColumn(['fecha_programada', 'franja']);
        });

        $soportaFk = DB::connection()->getDriverName() !== 'sqlite';

        Schema::table('soportes', function (Blueprint $table) use ($soportaFk) {
            $table->dropIndex(['canal_ingreso']);
            $table->dropIndex(['criticidad', 'sla_vence_at']);
            $table->dropIndex(['tecnico_soporte_id']);

            if ($soportaFk) {
                $table->dropForeign(['tecnico_soporte_id']);
            }

            $table->renameColumn('tecnico_soporte_id', 'ingeniero_asignado_id');
        });

        Schema::table('soportes', function (Blueprint $table) use ($soportaFk) {
            if ($soportaFk) {
                $table->foreign('ingeniero_asignado_id')->references('id')->on('users')->nullOnDelete();
                $table->dropConstrainedForeignId('escalado_a_id');
            } else {
                $table->dropColumn('escalado_a_id');
            }

            $table->index('ingeniero_asignado_id');
            $table->string('prioridad')->default('media');
            $table->dropColumn([
                'canal_ingreso',
                'criticidad',
                'criticidad_manual',
                'motivo_criticidad',
                'sla_vence_at',
                'sla_pausado_at',
                'sla_minutos_restantes',
                'intentos_contacto',
                'proximo_intento_at',
                'fecha_respuesta_n3',
                'respuesta_n3',
            ]);
        });

        Schema::table('tipos_falla', function (Blueprint $table) {
            $table->dropColumn('critica');
        });
    }
};
