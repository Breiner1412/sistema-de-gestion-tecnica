<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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
