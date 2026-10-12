<?php

namespace Database\Seeders;

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

        // Datos de prueba solo fuera de producción.
        if (!app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
