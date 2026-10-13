<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Las fotos y las firmas de las visitas pasan del disco público al privado.
 *
 * Las rutas guardadas en la base no cambian ('firmas/...', 'evidencias/...'):
 * solo se mueve el archivo. Es idempotente: lo que ya está en el privado se
 * deja, y si no hay nada que mover no hace nada.
 */
return new class extends Migration
{
    private const CARPETAS = ['firmas', 'evidencias'];

    public function up(): void
    {
        $this->mover(Storage::disk('public'), Storage::disk('local'));
    }

    public function down(): void
    {
        $this->mover(Storage::disk('local'), Storage::disk('public'));
    }

    private function mover($origen, $destino): void
    {
        foreach (self::CARPETAS as $carpeta) {
            foreach ($origen->allFiles($carpeta) as $archivo) {
                if (! $destino->exists($archivo)) {
                    $destino->writeStream($archivo, $origen->readStream($archivo));
                }

                $origen->delete($archivo);
            }

            $origen->deleteDirectory($carpeta);
        }
    }
};
