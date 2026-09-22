<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Diagnostico;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\Plan;
use App\Models\Soporte;
use App\Models\SoporteCambioPlan;
use App\Models\SoporteCambioTitular;
use App\Models\Tecnico;
use App\Models\TipoFalla;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración. No debe ejecutarse en producción.
 *
 * Todos los clientes son SINTÉTICOS: nombres, cédulas y teléfonos inventados.
 * Lo que se conserva del caso real son las proporciones — qué porcentaje de
 * casos es lentitud, cuántos quedan sin contacto, cómo se reparte la carga —
 * porque eso es lo que hace creíble una demo sin exponer a nadie.
 */
class DemoSeeder extends Seeder
{
    /** Distribución tomada del histórico real del semestre. */
    private const MEZCLA_TIPOS = [
        Soporte::TIPO_SOPORTE_REMOTO => 61,
        Soporte::TIPO_CAMBIO_PLAN => 20,
        Soporte::TIPO_SIN_INTERNET => 14,
        Soporte::TIPO_CAMBIO_TITULAR => 5,
    ];

    /** El canal no existe en el Excel: esta mezcla es un supuesto razonable. */
    private const MEZCLA_CANALES = [
        Soporte::CANAL_CALL_CENTER => 55,
        Soporte::CANAL_WHATSAPP => 35,
        Soporte::CANAL_CAJA => 10,
    ];

    private const MEZCLA_CIERRES = [
        Soporte::ESTADO_SOLUCIONADO => 74,
        Soporte::ESTADO_CERRADO_SIN_CONTACTO => 12,
        Soporte::ESTADO_CANCELADO => 6,
        'abierto' => 8,
    ];

    public function run(): void
    {
        mt_srand(20260914); // reproducible: la demo se ve igual en cada máquina

        $clientes = $this->clientes();
        $this->inventario();
        $this->casos($clientes);
    }

    /* ---------------------------------------------------------------
     | Maestros
     * --------------------------------------------------------------- */

    /** @return \Illuminate\Support\Collection<int, Cliente> */
    private function clientes()
    {
        $datos = [
            ['TCF004901', 'Marta Lucía Ospina Rendón', '3105550101', 'Cra 12 # 34-18, Barrio Centro'],
            ['TCF004902', 'Hernán Darío Cifuentes Toro', '3105550102', 'Cll 45 # 8-22, Barrio La Playa'],
            ['C009301', 'Rosa Elvira Betancur Gil', '3105550103', 'Cra 7 # 19-40, Barrio San José'],
            ['C009302', 'Jorge Iván Restrepo Loaiza', '3105550104', 'Cll 22 # 15-07, Barrio El Prado'],
            ['C009303', 'Yolanda Patricia Agudelo Ruiz', '3105550105', 'Cra 30 # 52-11, Barrio Kennedy'],
            ['SG000801', 'Álvaro Alonso Quintero Mesa', '3105550106', 'Cll 9 # 3-55, Vereda La Suiza'],
            ['SG000802', 'Nubia Esperanza Zapata Ríos', '3105550107', 'Cra 18 # 27-63, Barrio Cuba'],
            ['TC007081', 'Distribuidora El Roble S.A.S.', '3105550108', 'Cll 14 # 21-30, Zona Industrial'],
            ['TC007082', 'Panadería La Espiga Ltda.', '3105550109', 'Cra 5 # 11-19, Barrio Centro'],
            ['TC007083', 'Ferretería Los Andes S.A.S.', '3105550110', 'Cll 33 # 9-88, Av. Circunvalar'],
            ['C009304', 'Diego Fernando Marín Cano', '3105550111', 'Cra 24 # 61-04, Barrio Álamos'],
            ['C009305', 'Gloria Amparo Henao Vélez', '3105550112', 'Cll 70 # 12-45, Barrio Pinares'],
        ];

        $planes = Plan::pluck('id', 'nombre');
        $nombresPlan = $planes->keys()->all();

        $creados = collect();

        foreach ($datos as $i => [$abonado, $nombre, $telefono, $direccion]) {
            // Cédulas sintéticas con un prefijo reconocible como de prueba.
            $cedula = (string) (1099900001 + $i);

            $cliente = Cliente::updateOrCreate(['codigo_abonado' => $abonado], [
                'cedula' => $cedula,
                'nombre' => $nombre,
                'telefono' => $telefono,
                'direccion' => $direccion,
                'estado' => 'activo',
            ]);

            Contrato::firstOrCreate(['numero_contrato' => 'CT-'.$abonado], [
                'cliente_id' => $cliente->id,
                'fecha_inicio' => now()->subMonths(24 - $i)->startOfMonth(),
                'estado' => 'activo',
                'valor_contrato' => 45000 + ($i * 11000),
            ]);

            $creados->push($cliente);
        }

        return $creados;
    }

    private function inventario(): void
    {
        $materiales = [
            ['Cable drop fibra óptica (m)', 'cable', 5000, 500, 900],
            ['Conector SC/APC', 'conector', 800, 100, 1500],
            ['ONU GPON', 'router', 60, 10, 145000],
            ['Roseta óptica', 'accesorio', 200, 30, 4500],
            ['Patch cord 1.5 m', 'cable', 150, 25, 6000],
            ['Decodificador HD', 'equipo', 40, 8, 98000],
        ];

        foreach ($materiales as [$nombre, $tipo, $total, $minimo, $precio]) {
            Inventario::firstOrCreate(['nombre' => $nombre], [
                'tipo' => $tipo,
                'cantidad_total' => $total,
                'cantidad_minima' => $minimo,
                'precio_unitario' => $precio,
            ]);
        }
    }

    /* ---------------------------------------------------------------
     | Casos
     * --------------------------------------------------------------- */

    private function casos($clientes): void
    {
        if (Soporte::exists()) {
            return; // no duplicar si ya se sembró
        }

        $asesores = User::where('rol', User::ROL_CALL_CENTER)->pluck('id')->all();
        $tecnicos = User::where('rol', User::ROL_TECNICO_SOPORTE)->pluck('id')->all();
        $redes = User::where('rol', User::ROL_INGENIERO_REDES)->pluck('id')->all();
        $tecnicosCampo = Tecnico::pluck('id')->all();
        $diagnosticos = Diagnostico::pluck('id')->all();
        $planes = Plan::pluck('id')->all();

        if (!$asesores || !$tecnicos || !$diagnosticos) {
            return;
        }

        // La carga real está muy concentrada: tres personas hacen el 94%.
        $tecnicosPonderados = array_merge(
            array_fill(0, 10, $tecnicos[0]),
            array_fill(0, 9, $tecnicos[1] ?? $tecnicos[0]),
            array_fill(0, 8, $tecnicos[2] ?? $tecnicos[0]),
            array_slice($tecnicos, 3),
        );

        $fallas = TipoFalla::all();
        $lentitud = $fallas->firstWhere('nombre', 'LENTITUD E INTERMITENCIA');

        for ($i = 0; $i < 90; $i++) {
            $tipo = $this->elegir(self::MEZCLA_TIPOS);
            $creado = $this->momentoHabilAleatorio(mt_rand(1, 55));
            $cliente = $clientes->random();

            $esSoporte = in_array($tipo, [Soporte::TIPO_SOPORTE_REMOTO, Soporte::TIPO_SIN_INTERNET], true);

            // 70% de los casos de soporte son lentitud e intermitencia.
            $falla = null;
            if ($esSoporte && $tipo === Soporte::TIPO_SOPORTE_REMOTO) {
                $falla = mt_rand(1, 100) <= 70 ? $lentitud : $fallas->random();
            }

            $soporte = new Soporte([
                'cliente_id' => $cliente->id,
                'contrato_id' => $cliente->contratos()->value('id'),
                'tipo_solicitud' => $tipo,
                'canal_ingreso' => $this->elegir(self::MEZCLA_CANALES),
                'servicio_afectado' => $esSoporte ? ($falla?->servicio === 'tv' ? 'tv' : 'internet') : null,
                'tipo_falla_id' => $falla?->id,
                'usuario_registra_id' => $asesores[array_rand($asesores)],
                'descripcion' => $this->descripcion($tipo, $falla?->nombre),
                'estado' => Soporte::ESTADO_PENDIENTE,
            ]);

            $soporte->created_at = $creado;
            $soporte->updated_at = $creado;
            $soporte->save();

            $desenlace = $this->elegir(self::MEZCLA_CIERRES);
            $tecnico = $tecnicosPonderados[array_rand($tecnicosPonderados)];

            $desenlace === 'abierto'
                ? $this->dejarAbierto($soporte, $tecnico, $redes, $tecnicosCampo, $creado)
                : $this->cerrar($soporte, $desenlace, $tecnico, $diagnosticos, $creado);

            if ($tipo === Soporte::TIPO_CAMBIO_PLAN && $planes) {
                SoporteCambioPlan::create([
                    'soporte_id' => $soporte->id,
                    'plan_anterior_id' => $planes[array_rand($planes)],
                    'plan_nuevo_id' => $planes[array_rand($planes)],
                    'tiempo_ejecucion' => ['inmediatamente', 'inicio_mes_siguiente', 'al_ejecutar_orden'][mt_rand(0, 2)],
                ]);
            }

            if ($tipo === Soporte::TIPO_CAMBIO_TITULAR) {
                SoporteCambioTitular::create([
                    'soporte_id' => $soporte->id,
                    'titular_anterior_id' => $cliente->id,
                    'titular_nuevo_cedula' => (string) (1099950001 + $i),
                    'titular_nuevo_nombre' => 'Titular Entrante '.($i + 1),
                    'incluye_cambio_plan' => (bool) mt_rand(0, 1),
                ]);
            }
        }
    }

    /** Cierra el caso con fechas coherentes hacia atrás en el tiempo. */
    private function cerrar(Soporte $soporte, string $estado, int $tecnico, array $diagnosticos, CarbonImmutable $creado): void
    {
        $asignado = $creado->addMinutes(mt_rand(5, 180));
        $cerrado = $asignado->addMinutes(mt_rand(20, 900));

        $soporte->forceFill([
            'tecnico_soporte_id' => $tecnico,
            'fecha_asignacion' => $asignado,
            'tiempo_respuesta' => $soporte->calendario()->minutosEntre($creado, $asignado),
            'estado' => $estado,
            'fecha_cierre' => $cerrado,
            'tiempo_resolucion' => $soporte->calendario()->minutosEntre($creado, $cerrado),
            'updated_at' => $cerrado,
        ]);

        if ($estado === Soporte::ESTADO_SOLUCIONADO) {
            $soporte->diagnostico_id = $diagnosticos[array_rand($diagnosticos)];
            $soporte->observaciones_cierre = 'Se valida con el usuario, se aplica el ajuste y el servicio queda operativo.';
        }

        if ($estado === Soporte::ESTADO_CERRADO_SIN_CONTACTO) {
            $soporte->intentos_contacto = 3;
            $soporte->observaciones_cierre = 'Tres intentos de contacto sin respuesta del abonado.';
        }

        if ($estado === Soporte::ESTADO_CANCELADO) {
            $soporte->motivo_cancelacion = 'El usuario informa que el servicio se restableció por su cuenta.';
        }

        $soporte->saveQuietly();

        $this->historial($soporte, null, Soporte::ESTADO_PENDIENTE, $creado);
        $this->historial($soporte, Soporte::ESTADO_PENDIENTE, Soporte::ESTADO_EN_PROCESO, $asignado);
        $this->historial($soporte, Soporte::ESTADO_EN_PROCESO, $estado, $cerrado);
    }

    /** Deja el caso vivo en alguno de los estados intermedios, para ver el semáforo. */
    private function dejarAbierto(Soporte $soporte, int $tecnico, array $redes, array $tecnicosCampo, CarbonImmutable $creado): void
    {
        $asignado = $creado->addMinutes(mt_rand(5, 120));

        $soporte->forceFill([
            'tecnico_soporte_id' => $tecnico,
            'fecha_asignacion' => $asignado,
            'tiempo_respuesta' => $soporte->calendario()->minutosEntre($creado, $asignado),
            'estado' => Soporte::ESTADO_EN_PROCESO,
        ])->saveQuietly();

        $this->historial($soporte, null, Soporte::ESTADO_PENDIENTE, $creado);
        $this->historial($soporte, Soporte::ESTADO_PENDIENTE, Soporte::ESTADO_EN_PROCESO, $asignado);

        $rama = mt_rand(1, 100);

        if ($rama <= 25 && $redes) {
            $soporte->escalarANivel3($redes[array_rand($redes)], 'Se sospecha corte en el troncal de la zona.', $tecnico);

            return;
        }

        if ($rama <= 55 && $tecnicosCampo) {
            $orden = OrdenTrabajo::create([
                'soporte_id' => $soporte->id,
                'tecnico_id' => $tecnicosCampo[array_rand($tecnicosCampo)],
                'tipo' => 'revision',
                'estado' => OrdenTrabajo::ESTADO_PROGRAMADA,
                'fecha_programada' => now()->addDays(mt_rand(0, 4))->toDateString(),
                'franja' => mt_rand(0, 1) ? 'manana' : 'tarde',
            ]);

            $soporte->cambiarEstado(
                Soporte::ESTADO_ENVIADO_TECNICO,
                "Visita programada para el {$orden->fecha_programada->format('d/m/Y')}.",
                $tecnico
            );

            return;
        }

        if ($rama <= 75) {
            $soporte->marcarSinContacto($tecnico);

            // A la mitad se les vence el reintento, para que el comando
            // `soportes:reintentar-contacto` tenga algo real que hacer en la demo.
            if (mt_rand(0, 1)) {
                $soporte->forceFill(['proximo_intento_at' => now()->subHours(mt_rand(1, 20))])->saveQuietly();
            }
        }

        // El resto se queda en proceso: son los que alimentan el semáforo.
    }

    private function historial(Soporte $soporte, ?string $desde, string $hacia, CarbonImmutable $cuando): void
    {
        $soporte->historialEstados()->create([
            'estado_anterior' => $desde,
            'estado_nuevo' => $hacia,
            'usuario_id' => $soporte->tecnico_soporte_id ?? $soporte->usuario_registra_id,
            'created_at' => $cuando,
            'updated_at' => $cuando,
        ]);
    }

    /* ---------------------------------------------------------------
     | Utilidades
     * --------------------------------------------------------------- */

    /** Elige una clave según los pesos del arreglo. */
    private function elegir(array $pesos): string
    {
        $total = array_sum($pesos);
        $tiro = mt_rand(1, $total);

        foreach ($pesos as $clave => $peso) {
            $tiro -= $peso;

            if ($tiro <= 0) {
                return (string) $clave;
            }
        }

        return (string) array_key_first($pesos);
    }

    /** Un instante dentro de la jornada, hace N días, evitando fines de semana. */
    private function momentoHabilAleatorio(int $diasAtras): CarbonImmutable
    {
        $fecha = CarbonImmutable::now()->subDays($diasAtras)->setTime(mt_rand(7, 17), mt_rand(0, 11) * 5);

        while (in_array($fecha->dayOfWeekIso, [6, 7], true)) {
            $fecha = $fecha->subDay();
        }

        return $fecha;
    }

    private function descripcion(string $tipo, ?string $falla): string
    {
        return match ($tipo) {
            Soporte::TIPO_SIN_INTERNET => 'El abonado reporta que no tiene servicio de internet desde anoche. Bombillos del equipo en rojo.',
            Soporte::TIPO_CAMBIO_PLAN => 'El abonado solicita cambio de plan por temas de consumo y economía.',
            Soporte::TIPO_CAMBIO_TITULAR => 'Se solicita cambio de titular del contrato por traspaso del inmueble.',
            default => $falla
                ? "El abonado reporta {$falla} de forma recurrente durante el día."
                : 'El abonado reporta una falla en el servicio.',
        };
    }
}
