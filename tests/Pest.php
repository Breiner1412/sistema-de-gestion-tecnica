<?php

use App\Models\Cliente;
use App\Models\Diagnostico;
use App\Models\OrdenTrabajo;
use App\Models\Soporte;
use App\Models\Tecnico;
use App\Models\TipoFalla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

/*
 * Imágenes de verdad, minúsculas y escritas a mano: no se genera nada con GD
 * para que las pruebas no dependan de una extensión instalada.
 */
const PNG_MINIMO = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAFUlEQVR4nGNkYGD4z4AEmBjQAGEBAEEUAQeklUeXAAAAAElFTkSuQmCC';

const JPEG_MINIMO = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAAEAAQDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAAAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AJ//2Q==';

/** Lo que manda el teléfono es una data URL en base64, no una ruta. */
function fotoDePrueba(): string
{
    return 'data:image/jpeg;base64,'.JPEG_MINIMO;
}

/** Una visita programada, con su técnico y su caso ya enviado a terreno. */
function visitaDePrueba(?User $usuario = null, array $atributos = []): OrdenTrabajo
{
    $usuario ??= usuarioCon(User::ROL_TECNICO_CAMPO, 'Técnico de campo');

    $tecnico = Tecnico::create([
        'user_id' => $usuario->id,
        'nombre' => $usuario->name,
        'estado' => 'activo',
    ]);

    $soporte = soporteDePrueba();
    $soporte->cambiarEstado(Soporte::ESTADO_EN_PROCESO);
    $soporte->cambiarEstado(Soporte::ESTADO_ENVIADO_TECNICO, 'Se agenda visita en terreno.');

    return OrdenTrabajo::create(array_merge([
        'soporte_id' => $soporte->id,
        'tecnico_id' => $tecnico->id,
        'tipo' => 'revision',
        'estado' => OrdenTrabajo::ESTADO_PROGRAMADA,
        'fecha_programada' => today(),
        'franja' => 'manana',
    ], $atributos));
}

/** @return array<string, mixed> */
function cierreCompleto(array $extra = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'estado' => OrdenTrabajo::ESTADO_COMPLETADO,
        'diagnostico_id' => diagnosticoDePrueba()->id,
        'observaciones' => 'Se cambió el conector de la acometida y quedó en -18 dBm.',
    ], $extra);
}

/** Escribe un par de CSV temporales con el mismo formato que los del histórico. */
function csvDePrueba(array $casos): array
{
    $carpeta = sys_get_temp_dir().'/sgt-'.uniqid();
    mkdir($carpeta);

    $clientes = $carpeta.'/clientes.csv';
    $fh = fopen($clientes, 'w');
    fputcsv($fh, ['codigo_abonado', 'cedula', 'nombre', 'telefono']);
    fputcsv($fh, ['C100001', '1090000001', 'Ana Acosta Franco', '3105000001']);
    fputcsv($fh, ['C100002', '1090000002', 'Carlos Bermúdez Nieto', '3105000002']);
    fclose($fh);

    $archivo = $carpeta.'/casos.csv';
    $fh = fopen($archivo, 'w');
    fputcsv($fh, ['codigo_abonado', 'tipo_solicitud', 'canal_ingreso', 'servicio_afectado', 'falla',
        'diagnostico', 'estado', 'fecha_ingreso', 'fecha_cierre', 'asesor_idx', 'tecnico_idx', 'escalado']);

    foreach ($casos as $caso) {
        fputcsv($fh, $caso);
    }

    fclose($fh);

    return [$archivo, $clientes];
}

function importar(array $casos): void
{
    [$archivo, $clientes] = csvDePrueba($casos);

    test()->artisan('soportes:importar-historico', [
        '--casos' => $archivo,
        '--clientes' => $clientes,
    ])->assertSuccessful();
}
