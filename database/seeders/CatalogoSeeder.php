<?php

namespace Database\Seeders;

use App\Models\Diagnostico;
use App\Models\Plan;
use App\Models\TipoFalla;
use Illuminate\Database\Seeder;

/**
 * Catálogos tomados de la hoja CONFIGURACIÓN del Excel de la operación.
 * Es conocimiento del dominio, no dato personal: se conserva tal cual.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $diagnosticos = [
            'FALLA DE FIBRA',
            'WIFI DESACTIVADO',
            'WIFI RESETEADO',
            'MTU 1500',
            'CUENTA PPPoE BORRADA',
            'ONU RESETEADA',
            'FALLA TV',
            'FALLA MASIVA',
            'EQUIPO NO ENGANCHA',
            'VLAN DUPLICADA',
            'BLOQUEO DE EQUIPO',
            'ERROR DNS',
            'NIVELES CRÍTICOS',
        ];

        foreach ($diagnosticos as $nombre) {
            Diagnostico::firstOrCreate(['nombre' => $nombre]);
        }

        // 'critica' significa servicio caído: dispara criticidad inmediata.
        $fallas = [
            ['LENTITUD E INTERMITENCIA', 'internet', false],
            ['ERROR EN INGRESO A PÁGINAS', 'internet', false],
            ['PROBLEMAS CON TEST DE VELOCIDAD', 'internet', false],
            ['HABILITACIÓN DE PUERTOS', 'internet', false],
            ['PROBLEMAS CON CÁMARAS/DVR', 'internet', false],
            ['STREAMING CON FALLAS', 'ambos', false],
            ['PROBLEMAS CON TRANSMISIONES EN VIVO', 'ambos', false],
            ['PROGRAMACIÓN DE TV', 'tv', false],
            ['CANALES LLUVIOSOS/PIXELADOS', 'tv', false],
            ['CANALES FALTANTES', 'tv', false],
            ['CANALES INTERMITENTES', 'tv', false],
            ['SIN SEÑAL DE TV', 'tv', true],
            ['FALLAS CON LA TV', 'tv', true],
        ];

        foreach ($fallas as [$nombre, $servicio, $critica]) {
            TipoFalla::updateOrCreate(
                ['nombre' => $nombre],
                ['servicio' => $servicio, 'critica' => $critica],
            );
        }

        $planes = [
            ['5 MG', 5, 'hogar'],
            ['10 MG', 10, 'hogar'],
            ['15 MG', 15, 'hogar'],
            ['20 MG', 20, 'hogar'],
            ['30 MG', 30, 'hogar'],
            ['40 MG', 40, 'hogar'],
            ['50 MG', 50, 'hogar'],
            ['60 MG', 60, 'hogar'],
            ['70 MG', 70, 'hogar'],
            ['100 MG', 100, 'hogar'],
            ['125 MG', 125, 'hogar'],
            ['140 MG', 140, 'hogar'],
            ['150 MG', 150, 'hogar'],
            ['200 MG', 200, 'hogar'],
            ['250 MG', 250, 'hogar'],
            ['300 MG', 300, 'hogar'],
            ['400 MG', 400, 'hogar'],
            ['600 MG', 600, 'hogar'],
            ['800 MG', 800, 'hogar'],
            ['SOLO @ 30 MG', 30, 'solo_internet'],
            ['SOLO @ 50 MG', 50, 'solo_internet'],
            ['SOLO @ 70 MG', 70, 'solo_internet'],
            ['SOLO @ 100 MG', 100, 'solo_internet'],
            ['SOLO @ 150 MG', 150, 'solo_internet'],
            ['SOLO @ 200 MG', 200, 'solo_internet'],
            ['LR 60 MG', 60, 'larga_reserva'],
            ['LR 80 MG', 80, 'larga_reserva'],
            ['LR 120 MG', 120, 'larga_reserva'],
            ['VIP 150 MG', 150, 'vip'],
            ['VIP 210 MG', 210, 'vip'],
        ];

        foreach ($planes as [$nombre, $velocidad, $categoria]) {
            Plan::firstOrCreate(['nombre' => $nombre], [
                'velocidad_mb' => $velocidad,
                'categoria' => $categoria,
            ]);
        }
    }
}
