<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ModoDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Devuelve la demo pública a su estado de fábrica.
 *
 * La demo la usan desconocidos con cuentas compartidas: lo que uno crea,
 * cancela o cierra lo ve el siguiente. Cada noche se borra todo y se vuelve a
 * sembrar, para que nadie herede el desorden de otro por más de un día.
 *
 * Se niega a correr fuera del modo demo. Es un migrate:fresh: contra una base
 * de producción real borraría la operación entera.
 */
class ReiniciarDemo extends Command
{
    protected $signature = 'demo:reiniciar
                            {--sin-historico : No importa los 5.000 casos del histórico (más rápido)}';

    protected $description = 'Borra los datos de la demo pública y la vuelve a sembrar';

    public function handle(): int
    {
        if (! ModoDemo::activo()) {
            $this->error('Solo corre con DEMO_MODE=true: borra la base entera.');

            return self::FAILURE;
        }

        $inicio = microtime(true);

        $this->borrarSubidas();

        // Sesiones, cola y caché viven en la base: todos los visitantes salen.
        $this->call('migrate:fresh', ['--force' => true, '--seed' => true]);

        if (! $this->option('sin-historico')) {
            $this->call('soportes:importar-historico');
            $this->call('db:seed', ['--class' => 'ContratoHistoricoSeeder', '--force' => true]);
        }

        $this->info(sprintf('Demo reiniciada en %.1f s.', microtime(true) - $inicio));

        return self::SUCCESS;
    }

    /** Las fotos y firmas que subieron los visitantes no sobreviven al reinicio. */
    private function borrarSubidas(): void
    {
        foreach (config('demo.reinicio.discos') as $disco) {
            foreach (config('demo.reinicio.carpetas') as $carpeta) {
                Storage::disk($disco)->deleteDirectory($carpeta);
            }
        }
    }
}
