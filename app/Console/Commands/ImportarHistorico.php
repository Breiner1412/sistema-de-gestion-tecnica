<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Diagnostico;
use App\Models\Soporte;
use App\Models\TipoFalla;
use App\Models\User;
use App\Support\CalendarioHabil;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga el histórico de la operación desde los CSV de database/data.
 *
 * Esos CSV salieron del Excel "2026 - SOPORTE TÉCNICO INTERNO" y están
 * SEUDONIMIZADOS: cada abonado real quedó mapeado a un cliente sintético
 * estable, el personal quedó reducido a un índice de carga, y el texto libre
 * de las observaciones —que a veces traía nombres y teléfonos— se descartó
 * entero. Lo que se conserva es lo que tiene valor analítico: cuándo entró
 * cada caso, de qué tipo era, qué falla se reportó, qué se diagnosticó, en
 * qué estado terminó y cuánto se demoró.
 *
 * Dos advertencias sobre la fidelidad de los datos:
 *  - El canal de ingreso NO existe en el Excel. Se genera con una mezcla
 *    plausible para que el panel tenga algo que mostrar.
 *  - El Excel nunca registró a qué hora se tomó un caso, así que el tiempo
 *    de respuesta de los casos importados queda vacío. Solo hay tiempo de
 *    resolución.
 */
class ImportarHistorico extends Command
{
    protected $signature = 'soportes:importar-historico
                            {--casos=database/data/historico_casos.csv : CSV de casos}
                            {--clientes=database/data/historico_clientes.csv : CSV de clientes}
                            {--fresh : Borra los casos existentes antes de importar}
                            {--limit=0 : Importa solo los primeros N casos}';

    protected $description = 'Importa el histórico seudonimizado de la operación desde CSV';

    private const LOTE = 500;

    public function handle(): int
    {
        $rutaCasos = $this->resolver($this->option('casos'));
        $rutaClientes = $this->resolver($this->option('clientes'));

        foreach ([$rutaCasos, $rutaClientes] as $ruta) {
            if (!is_readable($ruta)) {
                $this->error("No se pudo leer {$ruta}");

                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            if (!$this->confirm('Esto borra TODOS los casos y sus visitas. ¿Continuar?', false)) {
                return self::FAILURE;
            }

            $this->limpiar();
        }

        $clientes = $this->importarClientes($rutaClientes);
        $this->importarCasos($rutaCasos, $clientes);

        return self::SUCCESS;
    }

    /** Acepta rutas absolutas (útil en pruebas) o relativas a la raíz del proyecto. */
    private function resolver(string $ruta): string
    {
        $absoluta = str_starts_with($ruta, DIRECTORY_SEPARATOR)
            || str_starts_with($ruta, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta) === 1;

        return $absoluta ? $ruta : base_path($ruta);
    }

    private function limpiar(): void
    {
        DB::table('soportes')->delete(); // las visitas e historial caen por cascada
        $this->warn('Casos existentes eliminados.');
    }

    /* ---------------------------------------------------------------
     | Clientes
     * --------------------------------------------------------------- */

    /** @return array<string, int> abonado => id */
    private function importarClientes(string $ruta): array
    {
        $this->info('Importando clientes...');

        $existentes = Cliente::whereNotNull('codigo_abonado')->pluck('id', 'codigo_abonado')->all();
        $lote = [];
        $nuevos = 0;

        foreach ($this->filas($ruta) as $fila) {
            if (isset($existentes[$fila['codigo_abonado']])) {
                continue;
            }

            $lote[] = [
                'codigo_abonado' => $fila['codigo_abonado'],
                'cedula' => $fila['cedula'],
                'nombre' => $fila['nombre'],
                'telefono' => $fila['telefono'],
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($lote) >= self::LOTE) {
                DB::table('clientes')->insert($lote);
                $nuevos += count($lote);
                $lote = [];
            }
        }

        if ($lote) {
            DB::table('clientes')->insert($lote);
            $nuevos += count($lote);
        }

        $this->line("  {$nuevos} clientes nuevos.");

        return Cliente::whereNotNull('codigo_abonado')->pluck('id', 'codigo_abonado')->all();
    }

    /* ---------------------------------------------------------------
     | Casos
     * --------------------------------------------------------------- */

    /** @param array<string, int> $clientes */
    private function importarCasos(string $ruta, array $clientes): void
    {
        $this->info('Importando casos...');

        $fallas = TipoFalla::pluck('id', 'nombre')->all();
        $criticas = TipoFalla::where('critica', true)->pluck('nombre')->all();
        $diagnosticos = Diagnostico::pluck('id', 'nombre')->all();

        $tecnicos = User::where('rol', User::ROL_TECNICO_SOPORTE)->orderBy('id')->pluck('id')->all();
        $asesores = User::where('rol', User::ROL_CALL_CENTER)->orderBy('id')->pluck('id')->all();

        if (!$tecnicos || !$asesores) {
            $this->error('Faltan usuarios. Corre primero: php artisan db:seed --class=UsuarioSeeder');

            return;
        }

        $calendario = CalendarioHabil::desdeConfig();
        $consecutivos = $this->consecutivosPorAnio();
        $limite = (int) $this->option('limit');

        $lote = [];
        $importados = 0;
        $omitidos = 0;
        $barra = $this->output->createProgressBar();
        $barra->start();

        foreach ($this->filas($ruta) as $fila) {
            if ($limite > 0 && $importados >= $limite) {
                break;
            }

            $clienteId = $clientes[$fila['codigo_abonado']] ?? null;
            $ingreso = $fila['fecha_ingreso'] ? CarbonImmutable::parse($fila['fecha_ingreso']) : null;

            // Un puñado de filas del Excel traía fechas imposibles o en el futuro.
            if (!$clienteId || !$ingreso || $ingreso->isFuture()) {
                $omitidos++;
                continue;
            }

            $cierre = $fila['fecha_cierre'] ? CarbonImmutable::parse($fila['fecha_cierre']) : null;
            $estado = $fila['estado'] ?: Soporte::ESTADO_SOLUCIONADO;
            $cerrado = in_array($estado, Soporte::ESTADOS_FINALES, true);

            $anio = $ingreso->year;
            $consecutivos[$anio] = ($consecutivos[$anio] ?? 0) + 1;

            $fallaId = $fila['falla'] ? ($fallas[$fila['falla']] ?? null) : null;
            $inmediato = $fila['tipo_solicitud'] === Soporte::TIPO_SIN_INTERNET
                || ($fila['falla'] && in_array($fila['falla'], $criticas, true));

            $lote[] = [
                'numero_soporte' => sprintf('SGT-%d-%06d', $anio, $consecutivos[$anio]),
                'cliente_id' => $clienteId,
                'tipo_solicitud' => $fila['tipo_solicitud'],
                'canal_ingreso' => $fila['canal_ingreso'] ?: Soporte::CANAL_CALL_CENTER,
                'criticidad' => $inmediato ? Soporte::CRITICIDAD_INMEDIATA : Soporte::CRITICIDAD_NORMAL,
                'criticidad_manual' => false,
                'servicio_afectado' => $fila['servicio_afectado'] ?: null,
                'tipo_falla_id' => $fallaId,
                'diagnostico_id' => $fila['diagnostico'] ? ($diagnosticos[$fila['diagnostico']] ?? null) : null,
                'usuario_registra_id' => $asesores[((int) $fila['asesor_idx']) % count($asesores)],
                'tecnico_soporte_id' => $fila['tecnico_idx'] !== ''
                    ? $tecnicos[((int) $fila['tecnico_idx']) % count($tecnicos)]
                    : null,
                'descripcion' => $this->descripcion($fila),
                'observaciones_cierre' => $cerrado ? 'Caso importado del histórico de la operación.' : null,
                'estado' => $estado,
                'escalado_nivel_3' => $fila['escalado'] === '1',
                'fecha_escalamiento' => $fila['escalado'] === '1' ? $ingreso : null,
                // El Excel nunca registró la hora de asignación: no hay tiempo de respuesta.
                'fecha_asignacion' => null,
                'tiempo_respuesta' => null,
                'tiempo_resolucion' => $cerrado && $cierre ? $calendario->minutosEntre($ingreso, $cierre) : null,
                'fecha_cierre' => $cerrado ? ($cierre ?? $ingreso) : null,
                'sla_vence_at' => $cerrado ? null : $this->vencimiento($calendario, $ingreso, $inmediato),
                'intentos_contacto' => $estado === Soporte::ESTADO_CERRADO_SIN_CONTACTO ? 3 : 0,
                'created_at' => $ingreso,
                'updated_at' => $cierre ?? $ingreso,
            ];

            $importados++;

            if (count($lote) >= self::LOTE) {
                $this->guardar($lote);
                $barra->advance(count($lote));
                $lote = [];
            }
        }

        if ($lote) {
            $this->guardar($lote);
            $barra->advance(count($lote));
        }

        $barra->finish();
        $this->newLine(2);

        $this->info("Importados {$importados} casos.".($omitidos ? " Omitidos {$omitidos} por datos incompletos." : ''));
        $this->line('  El canal de ingreso es simulado y el tiempo de respuesta queda vacío:');
        $this->line('  ninguno de los dos existía en el Excel original.');
    }

    /** Inserta el lote y deja una marca de importación en el historial. */
    private function guardar(array $lote): void
    {
        DB::transaction(function () use ($lote) {
            DB::table('soportes')->insert($lote);

            $numeros = array_column($lote, 'numero_soporte');
            $ids = DB::table('soportes')->whereIn('numero_soporte', $numeros)->pluck('id', 'numero_soporte');

            $historial = [];

            foreach ($lote as $caso) {
                $historial[] = [
                    'soporte_id' => $ids[$caso['numero_soporte']],
                    'estado_anterior' => null,
                    'estado_nuevo' => $caso['estado'],
                    'usuario_id' => null,
                    'motivo' => 'Caso importado del histórico de la operación.',
                    'automatico' => true,
                    'created_at' => $caso['created_at'],
                    'updated_at' => $caso['created_at'],
                ];
            }

            DB::table('soporte_historial_estados')->insert($historial);
        });
    }

    /** @return array<int, int> año => último consecutivo usado */
    private function consecutivosPorAnio(): array
    {
        $consecutivos = [];

        foreach (DB::table('soportes')->whereNotNull('numero_soporte')->pluck('numero_soporte') as $numero) {
            if (preg_match('/^SGT-(\d{4})-(\d{6})$/', $numero, $partes)) {
                $anio = (int) $partes[1];
                $consecutivos[$anio] = max($consecutivos[$anio] ?? 0, (int) $partes[2]);
            }
        }

        return $consecutivos;
    }

    private function vencimiento(CalendarioHabil $calendario, CarbonImmutable $ingreso, bool $inmediato): string
    {
        $config = config($inmediato ? 'sla.tiempos.inmediata' : 'sla.tiempos.normal');

        $vence = ($config['unidad'] ?? 'horas') === 'dias'
            ? $calendario->sumarDias($ingreso, (int) $config['resolucion'])
            : $calendario->sumarHoras($ingreso, (float) $config['resolucion']);

        return $vence->format('Y-m-d H:i:s');
    }

    /** El texto libre del Excel se descartó: se reconstruye una descripción neutra. */
    private function descripcion(array $fila): string
    {
        return match ($fila['tipo_solicitud']) {
            Soporte::TIPO_SIN_INTERNET => 'El abonado reporta que se encuentra sin servicio de internet.',
            Soporte::TIPO_CAMBIO_PLAN => 'El abonado solicita un cambio de plan.',
            Soporte::TIPO_CAMBIO_TITULAR => 'Se solicita cambio de titular del contrato.',
            default => $fila['falla']
                ? 'El abonado reporta: '.mb_strtolower($fila['falla']).'.'
                : 'El abonado reporta una falla en el servicio.',
        };
    }

    /* ---------------------------------------------------------------
     | Lectura
     * --------------------------------------------------------------- */

    /** Lee el CSV fila por fila, sin cargarlo entero en memoria. */
    private function filas(string $ruta): \Generator
    {
        $fh = fopen($ruta, 'r');
        $encabezado = fgetcsv($fh);

        while (($datos = fgetcsv($fh)) !== false) {
            if ($datos === [null] || count($datos) !== count($encabezado)) {
                continue;
            }

            yield array_combine($encabezado, $datos);
        }

        fclose($fh);
    }
}
