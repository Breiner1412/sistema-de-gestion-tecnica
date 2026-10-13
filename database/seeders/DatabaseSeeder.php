<?php

namespace Database\Seeders;

use App\Support\ModoDemo;
use Illuminate\Database\Seeder;

/**
 * Con los eventos de modelo encendidos a propósito: Soporte numera el caso,
 * deriva la criticidad y arranca el SLA en sus eventos. Sembrar sin ellos
 * dejaba los casos demo sin número ni vencimiento, invisibles al semáforo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogoSeeder::class,
            UsuarioSeeder::class,
        ]);

        // Datos de prueba fuera de producción, o en la demo pública, que corre
        // con APP_ENV=production pero existe justamente para mostrarlos.
        if (! app()->isProduction() || ModoDemo::activo()) {
            $this->call(DemoSeeder::class);
        }
    }
}
