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
                            {--fresh : Borra los casos importados antes (los creados en la aplicación se conservan)}
                            {--actualizar : Refresca nombre y telefono de los clientes que ya existen}
                            {--limit=0 : Importa solo los primeros N casos}';

    protected $description = 'Importa el histórico seudonimizado de la operación desde CSV';

    private const LOTE = 500;

    private const NOTA_IMPORTACION = 'Caso importado del histórico de la operación.';

    /** El histórico real pesa menos de 1 MB: 20 es de sobra y frena lo absurdo. */
    private const MAX_BYTES = 20 * 1024 * 1024;

    /** Una fila del histórico ocupa unos 150 caracteres. */
    private const MAX_LINEA = 8192;

    private const COLUMNAS_CLIENTES = ['codigo_abonado', 'cedula', 'nombre', 'telefono'];

    private const COLUMNAS_CASOS = [
        'codigo_abonado', 'tipo_solicitud', 'canal_ingreso', 'servicio_afectado', 'falla', 'diagnostico',
        'estado', 'fecha_ingreso', 'fecha_cierre', 'asesor_idx', 'tecnico_idx', 'escalado',
    ];

    /** Filas que no tenían el número de columnas del encabezado. */
    private int $malformadas = 0;

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

        if ($this->option('fresh')
            && ! $this->confirm('Esto borra los casos importados del histórico y sus visitas. ¿Continuar?', false)) {
            return self::FAILURE;
        }

        // Todo o nada: si algo falla a mitad, la base queda como estaba, y no
        // con los casos borrados y el histórico a medias.
        try {
            DB::transaction(function () use ($rutaCasos, $rutaClientes) {
                if ($this->option('fresh')) {
                    $this->limpiar();
                }

                $clientes = $this->importarClientes($rutaClientes);
                $this->importarCasos($rutaCasos, $clientes);
            });
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('  No se importó nada: la base quedó como estaba.');

            return self::FAILURE;
        }

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

    /**
     * Borra solo lo que vino del histórico; los casos creados en la aplicación
     * se quedan. Las visitas y el historial caen por cascada.
     */
    private function limpiar(): void
    {
        $borrados = DB::table('soportes')
            ->where(fn ($q) => $q
                ->whereNotNull('origen_importacion')
                // Importados antes de que existiera la marca: se reconocen por
                // la nota automática que el importador deja en su historial.
                ->orWhereExists(fn ($h) => $h->from('soporte_historial_estados')
                    ->whereColumn('soporte_historial_estados.soporte_id', 'soportes.id')
                    ->whereNull('soporte_historial_estados.usuario_id')
                    ->where('soporte_historial_estados.motivo', self::NOTA_IMPORTACION)))
            ->delete();

        $this->warn("{$borrados} casos importados eliminados.");
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
        $refrescar = [];
        $omitidos = 0;

        foreach ($this->filas($ruta, self::COLUMNAS_CLIENTES) as $fila) {
            if (! $this->clienteValido($fila)) {
                $omitidos++;
                continue;
            }

            if (isset($existentes[$fila['codigo_abonado']])) {
                // Por defecto no se toca lo que ya esta: el importador se corre
                // muchas veces y no debe pisar lo que alguien edito a mano. Con
                // --actualizar si, que es como se llevan a la base los nombres
                // reasignados por datos:seudonimizar-nombres.
                if ($this->option('actualizar')) {
                    $refrescar[$existentes[$fila['codigo_abonado']]] = [
                        'nombre' => $fila['nombre'],
                        'telefono' => $fila['telefono'],
                    ];
                }

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

        $this->line("  {$nuevos} clientes nuevos.".($omitidos ? " {$omitidos} omitidos por datos incompletos." : ''));
        $this->avisarMalformadas();

        if ($refrescar !== []) {
            $this->refrescar($refrescar);
        }

        return Cliente::whereNotNull('codigo_abonado')->pluck('id', 'codigo_abonado')->all();
    }

    /**
     * Refresca los clientes que ya estaban.
     *
     * Son actualizaciones fila a fila dentro de una transaccion, y no un UPDATE
     * con CASE: cada cliente lleva valores distintos, son unos miles y esto se
     * corre a mano de vez en cuando. La version lista de leer gana.
     *
     * @param  array<int, array{nombre: string, telefono: string}>  $cambios
     */
    private function refrescar(array $cambios): void
    {
        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $id => $valores) {
                DB::table('clientes')->where('id', $id)->update($valores + ['updated_at' => now()]);
            }
        });

        $this->line('  '.count($cambios).' clientes actualizados.');
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
            throw new \RuntimeException('Faltan usuarios. Corre primero: php artisan db:seed --class=UsuarioSeeder');
        }

        $calendario = CalendarioHabil::desdeConfig();
        $consecutivos = $this->consecutivosPorAnio();
        $limite = (int) $this->option('limit');

        // Lo que ya entró en una corrida anterior se salta: reimportar no
        // duplica nada.
        $yaImportados = DB::table('soportes')->whereNotNull('origen_importacion')
            ->pluck('origen_importacion')->flip()->all();
        $vecesVista = [];

        $lote = [];
        $importados = 0;
        $repetidos = 0;
        $omitidos = 0;
        $barra = $this->output->createProgressBar();
        $barra->start();

        foreach ($this->filas($ruta, self::COLUMNAS_CASOS) as $fila) {
            if ($limite > 0 && $importados >= $limite) {
                break;
            }

            // La marca sale del contenido de la fila y de cuántas veces se ha
            // visto una fila idéntica: dos casos iguales del mismo abonado a la
            // misma hora siguen siendo dos, y el número de línea no importa.
            $contenido = implode('|', $fila);
            $vecesVista[$contenido] = ($vecesVista[$contenido] ?? 0) + 1;
            $origen = sha1($contenido.'#'.$vecesVista[$contenido]);

            if (isset($yaImportados[$origen])) {
                $repetidos++;
                continue;
            }

            $clienteId = $clientes[$fila['codigo_abonado']] ?? null;
            $ingreso = $this->fecha($fila['fecha_ingreso']);

            // Un puñado de filas del Excel traía fechas imposibles o en el
            // futuro, y un valor que el sistema no conoce rompería la máquina
            // de estados más tarde: se omiten, no tumban la importación.
            if (!$clienteId || !$ingreso || $ingreso->isFuture() || ! $this->casoValido($fila)) {
                $omitidos++;
                continue;
            }

            $cierre = $this->fecha($fila['fecha_cierre']);
            $estado = $fila['estado'] ?: Soporte::ESTADO_SOLUCIONADO;
            $cerrado = in_array($estado, Soporte::ESTADOS_FINALES, true);

            $anio = $ingreso->year;
            $consecutivos[$anio] = ($consecutivos[$anio] ?? 0) + 1;

            $fallaId = $fila['falla'] ? ($fallas[$fila['falla']] ?? null) : null;
            $inmediato = $fila['tipo_solicitud'] === Soporte::TIPO_SIN_INTERNET
                || ($fila['falla'] && in_array($fila['falla'], $criticas, true));

            $vence = $cerrado ? null : $this->vencimiento($calendario, $ingreso, $inmediato);

            // Lo abierto del histórico venció hace meses: se da por avisado
            // para que la primera corrida de soportes:alertar-sla no mande un
            // correo con cientos de casos viejos a gestión.
            $yaVencido = $vence?->isPast() ?? false;

            $lote[] = [
                'numero_soporte' => sprintf('SGT-%d-%06d', $anio, $consecutivos[$anio]),
                'origen_importacion' => $origen,
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
                'observaciones_cierre' => $cerrado ? self::NOTA_IMPORTACION : null,
                'estado' => $estado,
                'escalado_nivel_3' => $fila['escalado'] === '1',
                'fecha_escalamiento' => $fila['escalado'] === '1' ? $ingreso : null,
                // El Excel nunca registró la hora de asignación: no hay tiempo de respuesta.
                'fecha_asignacion' => null,
                'tiempo_respuesta' => null,
                'tiempo_resolucion' => $cerrado && $cierre ? $calendario->minutosEntre($ingreso, $cierre) : null,
                'fecha_cierre' => $cerrado ? ($cierre ?? $ingreso) : null,
                'sla_vence_at' => $vence?->format('Y-m-d H:i:s'),
                'sla_presupuesto_minutos' => $vence ? $calendario->minutosEntre($ingreso, $vence) : null,
                'alerta_sla_nivel' => $yaVencido ? 'vencido' : null,
                'alerta_sla_at' => $yaVencido ? now() : null,
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

        // Los números se pusieron aquí, sin pasar por el contador: se le
        // avisa, o la próxima alta desde la pantalla chocaría con ellos.
        foreach (array_keys($consecutivos) as $anio) {
            Soporte::sincronizarSecuencia($anio);
        }

        $this->info("Importados {$importados} casos."
            .($repetidos ? " {$repetidos} ya estaban importados." : '')
            .($omitidos ? " Omitidos {$omitidos} por datos incompletos o inválidos." : ''));
        $this->avisarMalformadas();
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
                    'motivo' => self::NOTA_IMPORTACION,
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

    private function vencimiento(CalendarioHabil $calendario, CarbonImmutable $ingreso, bool $inmediato): CarbonImmutable
    {
        $config = config($inmediato ? 'sla.tiempos.inmediata' : 'sla.tiempos.normal');

        return ($config['unidad'] ?? 'horas') === 'dias'
            ? $calendario->sumarDias($ingreso, (int) $config['resolucion'])
            : $calendario->sumarHoras($ingreso, (float) $config['resolucion']);
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
    /**
     * Lee el CSV fila por fila, sin cargarlo entero en memoria.
     *
     * Lo que llega de un Excel no es limpio: BOM al inicio, Latin-1 en vez de
     * UTF-8, filas vacías al final, alguna fila partida. Aquí se normaliza, y
     * lo que no tiene arreglo se cuenta en vez de desaparecer en silencio.
     *
     * @param  array<int, string>  $requeridas
     */
    private function filas(string $ruta, array $requeridas): \Generator
    {
        if (filesize($ruta) > self::MAX_BYTES) {
            throw new \RuntimeException(sprintf('%s pesa más de %d MB.', basename($ruta), self::MAX_BYTES / 1048576));
        }

        $fh = fopen($ruta, 'r');

        if ($fh === false) {
            throw new \RuntimeException("No se pudo abrir {$ruta}");
        }

        try {
            $encabezado = fgetcsv($fh, self::MAX_LINEA, ',', '"', '');

            if (! $encabezado || $encabezado === [null]) {
                throw new \RuntimeException(basename($ruta).' está vacío.');
            }

            $encabezado = array_map(fn ($columna) => trim($this->utf8((string) $columna)), $encabezado);
            $encabezado[0] = preg_replace('/^\x{FEFF}/u', '', $encabezado[0]);

            $faltan = array_diff($requeridas, $encabezado);

            if ($faltan !== []) {
                throw new \RuntimeException('Faltan columnas en '.basename($ruta).': '.implode(', ', $faltan));
            }

            while (($datos = fgetcsv($fh, self::MAX_LINEA, ',', '"', '')) !== false) {
                if ($datos === [null]) {
                    continue; // línea vacía
                }

                if (count($datos) !== count($encabezado)) {
                    $this->malformadas++;
                    continue;
                }

                yield array_combine($encabezado, array_map(fn ($valor) => trim($this->utf8((string) $valor)), $datos));
            }
        } finally {
            fclose($fh);
        }
    }

    /** Un CSV guardado desde Excel en Windows suele venir en Windows-1252. */
    private function utf8(string $valor): string
    {
        return mb_check_encoding($valor, 'UTF-8') ? $valor : mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
    }

    /** Solo el formato del histórico; nada de "next monday" ni fechas que PHP corrige solas. */
    private function fecha(string $valor): ?CarbonImmutable
    {
        if ($valor === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $formato) {
            try {
                $fecha = CarbonImmutable::createFromFormat($formato, $valor);
            } catch (\InvalidArgumentException) {
                continue; // Carbon 3 lanza en vez de devolver false
            }

            // Si al volver a escribirla no da lo mismo, PHP la "corrigió"
            // (31 de febrero, hora 25): era una fecha imposible.
            if ($fecha && $fecha->format($formato) === $valor) {
                return $fecha;
            }
        }

        return null;
    }

    /** Tipo, estado, canal e índices tienen que ser valores que el sistema conoce. */
    private function casoValido(array $fila): bool
    {
        $indice = fn (string $valor) => $valor === '' || ctype_digit($valor);

        return array_key_exists($fila['tipo_solicitud'], Soporte::TIPOS_SOLICITUD)
            && ($fila['estado'] === '' || array_key_exists($fila['estado'], Soporte::ESTADOS))
            && ($fila['canal_ingreso'] === '' || array_key_exists($fila['canal_ingreso'], Soporte::CANALES))
            && in_array($fila['escalado'], ['', '0', '1'], true)
            && $indice($fila['asesor_idx'])
            && $indice($fila['tecnico_idx']);
    }

    private function clienteValido(array $fila): bool
    {
        return $fila['codigo_abonado'] !== '' && mb_strlen($fila['codigo_abonado']) <= 30
            && $fila['cedula'] !== '' && mb_strlen($fila['cedula']) <= 30
            && $fila['nombre'] !== '' && mb_strlen($fila['nombre']) <= 255
            && mb_strlen($fila['telefono']) <= 30;
    }

    private function avisarMalformadas(): void
    {
        if ($this->malformadas > 0) {
            $this->warn("  {$this->malformadas} fila(s) con columnas de más o de menos: no se importaron.");
            $this->malformadas = 0;
        }
    }
}
