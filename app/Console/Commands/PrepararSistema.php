<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ModoDemo;
use Illuminate\Console\Command;

/**
 * Lo que hace soporte-app cada vez que arranca: dejar la base al día.
 *
 * Migra siempre. Si la base está vacía (primer arranque), además la siembra:
 * en la demo, con todo lo de demo:reiniciar (casos, histórico y contratos);
 * en una operación real, solo el administrador del .env y los catálogos.
 * Si ya hay usuarios no toca nada más, así reiniciar el contenedor nunca
 * borra ni duplica datos.
 */
class PrepararSistema extends Command
{
    protected $signature = 'sistema:preparar';

    protected $description = 'Migra la base y la siembra si está vacía (arranque del contenedor)';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        if (User::query()->exists()) {
            $this->info('La base ya tenía datos: no se siembra nada.');

            return self::SUCCESS;
        }

        if (ModoDemo::activo()) {
            $this->info('Base vacía en modo demo: se carga la demo completa.');

            return $this->call('demo:reiniciar');
        }

        $this->info('Base vacía: se crean el administrador y los catálogos.');

        return $this->call('db:seed', ['--force' => true]);
    }
}
