<?php

use App\Models\Cliente;
use App\Models\Diagnostico;
use App\Models\Soporte;
use App\Models\TipoFalla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Ayudas del dominio
|--------------------------------------------------------------------------
| Las pruebas necesitan casos de soporte con su cliente y su equipo alrededor.
| Estas funciones arman lo mínimo para que cada prueba diga solo lo suyo.
*/

function calendarioDePrueba(): \App\Support\CalendarioHabil
{
    // Jornada de 7:00 a 18:00, de lunes a viernes, sin cierres propios.
    return new \App\Support\CalendarioHabil(7, 18, [1, 2, 3, 4, 5], []);
}

function usuarioCon(string $rol, string $nombre = 'Usuario de prueba'): User
{
    static $consecutivo = 0;
    $consecutivo++;

    return User::create([
        'name' => $nombre,
        'email' => "usuario{$consecutivo}@prueba.local",
        'password' => Hash::make('secreto123'),
        'rol' => $rol,
        'estado' => 'activo',
    ]);
}

function clienteDePrueba(): Cliente
{
    static $consecutivo = 0;
    $consecutivo++;

    return Cliente::create([
        'codigo_abonado' => sprintf('TCF%06d', $consecutivo),
        'cedula' => (string) (1099900000 + $consecutivo),
        'nombre' => "Cliente de prueba {$consecutivo}",
        'estado' => 'activo',
    ]);
}

function fallaDePrueba(bool $critica = false): TipoFalla
{
    static $consecutivo = 0;
    $consecutivo++;

    return TipoFalla::create([
        'nombre' => ($critica ? 'FALLA CRITICA ' : 'FALLA NORMAL ').$consecutivo,
        'servicio' => 'internet',
        'critica' => $critica,
        'activo' => true,
    ]);
}

function diagnosticoDePrueba(): Diagnostico
{
    static $consecutivo = 0;
    $consecutivo++;

    return Diagnostico::create(['nombre' => "DIAGNOSTICO {$consecutivo}", 'activo' => true]);
}

function soporteDePrueba(array $atributos = []): Soporte
{
    return Soporte::create(array_merge([
        'cliente_id' => clienteDePrueba()->id,
        'tipo_solicitud' => Soporte::TIPO_SOPORTE_REMOTO,
        'canal_ingreso' => Soporte::CANAL_CALL_CENTER,
        'servicio_afectado' => 'internet',
        'usuario_registra_id' => usuarioCon(User::ROL_CALL_CENTER)->id,
        'descripcion' => 'El abonado reporta lentitud desde ayer en la noche.',
        'estado' => Soporte::ESTADO_PENDIENTE,
    ], $atributos));
}
