<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\EquipoInstalado;
use App\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Contratos para los abonados importados del histórico.
 *
 * El Excel de la operación era una hoja de incidencias: traía el caso, la falla
 * y quién lo atendió, pero nada del contrato ni del equipo instalado, porque esa
 * información vivía en el ERP del ISP y nunca pasó por ahí. Resultado: los 4.114
 * abonados importados quedan sin contrato, y la ficha del cliente muestra una
 * sección vacía que parece un error del sistema y no un hueco del origen.
 *
 * Esto lo rellena, y conviene decir con todas las letras qué es: **datos
 * inventados**. No salen del histórico ni se deducen de él. Las fechas, los
 * planes y los seriales son plausibles y nada más. Se separa del importador
 * justamente para que la línea quede clara: `soportes:importar-historico` trae
 * lo que hubo, este seeder rellena lo que nunca hubo.
 *
 * Es determinista —todo sale del código de abonado— para que dos personas que
 * clonen el repositorio vean exactamente la misma demo.
 */
class ContratoHistoricoSeeder extends Seeder
{
    /** Proporción de contratos en cada estado, al ojo de una operación normal. */
    private const ESTADOS = [
        'activo' => 88,
        'suspendido' => 8,
        'retirado' => 4,
    ];

    private const EQUIPOS = [
        ['router', 'Huawei HG8145V5'],
        ['router', 'ZTE F670L'],
        ['router', 'Nokia G-140W-C'],
        ['onu', 'Huawei HG8010H'],
        ['decodificador', 'Zapper HD-2200'],
    ];

    public function run(): void
    {
        // values(): el índice numérico se usa para repartir, y get() puede
        // devolver claves que no arranquen en cero.
        $planes = Plan::activos()->get(['id', 'precio'])->values();

        if ($planes->isEmpty()) {
            $this->command?->warn('No hay planes en el catálogo; corre CatalogoSeeder primero.');

            return;
        }

        $creados = 0;

        // Solo los abonados del histórico —los que tienen código— y solo los que
        // todavía no tienen contrato: correr esto dos veces no debe duplicar nada.
        Cliente::whereNotNull('codigo_abonado')
            ->whereDoesntHave('contratos')
            ->select('id', 'codigo_abonado')
            ->chunkById(500, function ($clientes) use ($planes, &$creados) {
                $contratos = [];
                $equipos = [];

                foreach ($clientes as $cliente) {
                    $semilla = crc32($cliente->codigo_abonado);
                    $plan = $planes[$semilla % $planes->count()];
                    $inicio = $this->fechaDeInicio($semilla);
                    $estado = $this->estado($semilla);

                    $contratos[] = [
                        'cliente_id' => $cliente->id,
                        'numero_contrato' => 'CT-'.$cliente->codigo_abonado,
                        'fecha_inicio' => $inicio->toDateString(),
                        // Solo el retirado tiene fecha de fin; los demás siguen corriendo.
                        'fecha_fin' => $estado === 'retirado'
                            ? $inicio->addMonths(6 + $semilla % 24)->toDateString()
                            : null,
                        'estado' => $estado,
                        'valor_contrato' => $plan->precio,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $equipos[] = [
                        'codigo' => $cliente->codigo_abonado,
                        'semilla' => $semilla,
                        'inicio' => $inicio,
                        'estado' => $estado,
                    ];
                }

                DB::transaction(function () use ($contratos, $equipos, &$creados) {
                    DB::table('contratos')->insert($contratos);
                    $creados += count($contratos);

                    $this->instalarEquipos($equipos);
                });
            });

        $this->command?->info("Contratos creados para {$creados} abonados del histórico.");
    }

    /**
     * El equipo se inserta después del contrato porque necesita su id, y se
     * busca por número de contrato, que es único y lo acabamos de escribir.
     *
     * @param  array<int, array{codigo: string, semilla: int, inicio: CarbonImmutable, estado: string}>  $equipos
     */
    private function instalarEquipos(array $equipos): void
    {
        $ids = DB::table('contratos')
            ->whereIn('numero_contrato', array_map(fn($e) => 'CT-'.$e['codigo'], $equipos))
            ->pluck('id', 'numero_contrato');

        $filas = [];

        foreach ($equipos as $equipo) {
            $contratoId = $ids['CT-'.$equipo['codigo']] ?? null;

            if (! $contratoId) {
                continue;
            }

            [$tipo, $modelo] = self::EQUIPOS[$equipo['semilla'] % count(self::EQUIPOS)];

            $filas[] = [
                'contrato_id' => $contratoId,
                'tipo' => $tipo,
                'modelo' => $modelo,
                // Serial estable y con pinta de serial, derivado del abonado.
                'numero_serie' => strtoupper(substr(md5($equipo['codigo'].$modelo), 0, 12)),
                'fecha_instalacion' => $equipo['inicio']->toDateString(),
                'estado' => $equipo['estado'] === 'retirado' ? 'retirado' : 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($filas !== []) {
            EquipoInstalado::insert($filas);
        }
    }

    /** Entre hace cuatro años y hace un mes: una base de abonados no nace toda el mismo día. */
    private function fechaDeInicio(int $semilla): CarbonImmutable
    {
        return CarbonImmutable::today()->subDays(30 + $semilla % 1430);
    }

    private function estado(int $semilla): string
    {
        $punto = intdiv($semilla, 7) % 100;
        $acumulado = 0;

        foreach (self::ESTADOS as $estado => $porcentaje) {
            $acumulado += $porcentaje;

            if ($punto < $acumulado) {
                return $estado;
            }
        }

        return 'activo';
    }
}
