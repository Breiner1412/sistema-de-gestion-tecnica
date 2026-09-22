<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lo que hace falta para cerrar una visita desde el celular.
 *
 * Tres cosas:
 *
 *  - `cierre_uuid`: el celular puede quedarse sin señal a mitad del envío y
 *    reintentar después sin saber si el primero llegó. El identificador lo
 *    pone el teléfono antes de enviar, así que el servidor reconoce el
 *    reenvío y no cierra el caso dos veces ni descuenta el material dos veces.
 *
 *  - `cerrada_en_terreno_at`: la hora en que el técnico cerró en el celular,
 *    que no es la hora en que el servidor se enteró. Si cerró a las 10:05 en
 *    un sótano sin señal y sincronizó a las 14:30, las métricas tienen que
 *    creerle a las 10:05.
 *
 *  - `firma_path`: la firma deja de vivir como base64 dentro de la tabla.
 *    Una firma son unos 30 KB de texto que se arrastraban en cada consulta
 *    que tocara la orden, incluso cuando nadie iba a mirarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->uuid('cierre_uuid')->nullable()->unique()->after('estado');
            $table->dateTime('cerrada_en_terreno_at')->nullable()->after('hora_fin');
            $table->text('motivo_no_realizada')->nullable()->after('observaciones');
            $table->string('firma_path')->nullable()->after('longitud_fin');
        });

        Schema::table('evidencias', function (Blueprint $table) {
            $table->dateTime('tomada_at')->nullable()->after('longitud');
        });

        $this->mudarFirmas();

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn('firma_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->longText('firma_cliente')->nullable();
        });

        Schema::table('evidencias', function (Blueprint $table) {
            $table->dropColumn('tomada_at');
        });

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn(['cierre_uuid', 'cerrada_en_terreno_at', 'motivo_no_realizada', 'firma_path']);
        });
    }

    /**
     * Saca a disco las firmas que ya estaban guardadas como data URL.
     *
     * Se hace por lotes y sin modelo: es una migración, y el modelo de mañana
     * no tiene por qué seguir pareciéndose al de hoy.
     */
    private function mudarFirmas(): void
    {
        $disco = Storage::disk('public');

        DB::table('ordenes_trabajo')
            ->whereNotNull('firma_cliente')
            ->select('id', 'firma_cliente')
            ->orderBy('id')
            ->chunk(100, function ($ordenes) use ($disco) {
                foreach ($ordenes as $orden) {
                    $binario = $this->decodificar($orden->firma_cliente);

                    if ($binario === null) {
                        continue;
                    }

                    $ruta = 'firmas/'.Str::uuid().'.png';
                    $disco->put($ruta, $binario);

                    DB::table('ordenes_trabajo')->where('id', $orden->id)->update(['firma_path' => $ruta]);
                }
            });
    }

    /** Acepta tanto "data:image/png;base64,AAA..." como el base64 pelado. */
    private function decodificar(string $valor): ?string
    {
        $base64 = str_contains($valor, ',') ? Str::after($valor, ',') : $valor;

        $binario = base64_decode($base64, true);

        return $binario !== false && $binario !== '' ? $binario : null;
    }
};
