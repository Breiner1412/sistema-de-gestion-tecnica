<?php

use App\Models\Evidencia;
use App\Models\Inventario;
use App\Models\MaterialOrden;
use App\Models\OrdenTrabajo;
use App\Models\Soporte;
use App\Models\SoporteHistorialEstado;
use App\Models\Tecnico;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
});

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

it('cierra la visita y el caso en el mismo envío', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO, 'Andrés Loaiza');
    $visita = visitaDePrueba($usuario);

    $respuesta = $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto());

    $respuesta->assertOk()->assertJson(['ok' => true, 'repetido' => false]);

    $visita->refresh();

    expect($visita->estado)->toBe(OrdenTrabajo::ESTADO_COMPLETADO)
        ->and($visita->hora_fin)->not->toBeNull()
        ->and($visita->soporte->refresh()->estado)->toBe(Soporte::ESTADO_SOLUCIONADO);
});

it('guarda la foto y la firma en disco, no en la tabla', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto([
            'firma' => 'data:image/png;base64,'.PNG_MINIMO,
            'fotos' => [
                ['contenido' => fotoDePrueba(), 'descripcion' => 'Acometida', 'tomada_at' => now()->toIso8601String()],
                ['contenido' => fotoDePrueba()],
            ],
        ]))
        ->assertOk();

    $visita->refresh();

    expect($visita->firma_path)->toStartWith('firmas/')
        ->and(Storage::disk('public')->exists($visita->firma_path))->toBeTrue();

    $evidencias = Evidencia::where('orden_id', $visita->id)->get();

    expect($evidencias)->toHaveCount(2)
        ->and($evidencias->first()->descripcion)->toBe('Acometida');

    foreach ($evidencias as $evidencia) {
        expect(Storage::disk('public')->exists($evidencia->archivo_url))->toBeTrue();
    }
});

it('reconoce el reenvío del mismo cierre y no lo aplica dos veces', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $carga = cierreCompleto(['fotos' => [['contenido' => fotoDePrueba()]]]);

    $this->actingAs($usuario)->postJson(route('campo.cerrar', $visita), $carga)->assertOk();

    $transiciones = SoporteHistorialEstado::where('soporte_id', $visita->soporte_id)->count();

    // El mismo envío otra vez: es lo que hace la cola del teléfono cuando no
    // llegó a leer la respuesta del primero.
    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), $carga)
        ->assertOk()
        ->assertJson(['repetido' => true]);

    expect(Evidencia::where('orden_id', $visita->id)->count())->toBe(1)
        ->and(SoporteHistorialEstado::where('soporte_id', $visita->soporte_id)->count())->toBe($transiciones);
});

it('le cree al reloj del terreno y no al de la sincronización', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $enTerreno = now()->subHours(3);

    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto([
            'cerrada_en_terreno_at' => $enTerreno->toIso8601String(),
        ]))
        ->assertOk();

    $visita->refresh();

    expect($visita->cerrada_en_terreno_at->diffInMinutes($enTerreno, absolute: true))->toBeLessThan(2)
        ->and($visita->hora_fin->diffInMinutes($enTerreno, absolute: true))->toBeLessThan(2)
        ->and($visita->cerradaEnDiferido())->toBeFalse();
});

it('descarta una hora de terreno imposible', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    // Un celular con el reloj mal puesto no puede fechar la visita en el futuro.
    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto([
            'cerrada_en_terreno_at' => now()->addDays(3)->toIso8601String(),
        ]))
        ->assertOk();

    expect($visita->refresh()->cerrada_en_terreno_at->isFuture())->toBeFalse();
});

it('devuelve el caso a la cola cuando la visita no se pudo hacer', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), [
            'uuid' => (string) Str::uuid(),
            'estado' => OrdenTrabajo::ESTADO_NO_REALIZADA,
            'motivo' => 'No había nadie en la casa; se llamó dos veces.',
        ])
        ->assertOk();

    $visita->refresh();

    expect($visita->estado)->toBe(OrdenTrabajo::ESTADO_NO_REALIZADA)
        ->and($visita->motivo_no_realizada)->toContain('No había nadie')
        ->and($visita->soporte->refresh()->estado)->toBe(Soporte::ESTADO_EN_PROCESO);

    $ultima = SoporteHistorialEstado::where('soporte_id', $visita->soporte_id)->latest('id')->first();

    expect($ultima->motivo)->toContain('Visita no realizada');
});

it('exige diagnóstico y descripción para dar una visita por resuelta', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), [
            'uuid' => (string) Str::uuid(),
            'estado' => OrdenTrabajo::ESTADO_COMPLETADO,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['diagnostico_id', 'observaciones']);

    expect($visita->refresh()->estado)->toBe(OrdenTrabajo::ESTADO_PROGRAMADA);
});

it('exige el motivo cuando la visita no se pudo hacer', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), [
            'uuid' => (string) Str::uuid(),
            'estado' => OrdenTrabajo::ESTADO_NO_REALIZADA,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('motivo');
});

it('no deja que un técnico cierre la visita de otro', function () {
    $visita = visitaDePrueba();

    $intruso = usuarioCon(User::ROL_TECNICO_CAMPO, 'Otro técnico');
    Tecnico::create(['user_id' => $intruso->id, 'nombre' => $intruso->name, 'estado' => 'activo']);

    $this->actingAs($intruso)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto())
        ->assertForbidden();

    expect($visita->refresh()->estado)->toBe(OrdenTrabajo::ESTADO_PROGRAMADA);
});

it('descuenta el material declarado en el cierre', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $material = Inventario::create([
        'nombre' => 'Conector SC/APC',
        'tipo' => 'conector',
        'cantidad_total' => 10,
    ]);

    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto([
            'materiales' => [['material_id' => $material->id, 'cantidad' => 3]],
        ]))
        ->assertOk()
        ->assertJson(['avisos' => []]);

    expect($material->refresh()->cantidad_total)->toBe(7)
        ->and(MaterialOrden::where('orden_id', $visita->id)->count())->toBe(1);
});

it('cierra igual aunque el material ya no alcance, pero lo avisa', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    // Escenario real: el cierre salió del teléfono a las 10:05 y llegó a las
    // 14:30, cuando otro técnico ya se llevó lo que quedaba.
    $material = Inventario::create([
        'nombre' => 'Conector SC/APC',
        'tipo' => 'conector',
        'cantidad_total' => 1,
    ]);

    $respuesta = $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto([
            'materiales' => [['material_id' => $material->id, 'cantidad' => 5]],
        ]));

    $respuesta->assertOk();

    expect($respuesta->json('avisos'))->not->toBeEmpty()
        ->and($visita->refresh()->estado)->toBe(OrdenTrabajo::ESTADO_COMPLETADO)
        ->and($material->refresh()->cantidad_total)->toBe(1);
});

it('rechaza cerrar una visita que ya estaba cerrada', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $this->actingAs($usuario)->postJson(route('campo.cerrar', $visita), cierreCompleto())->assertOk();

    // Otro uuid: no es un reenvío, es un cierre distinto sobre algo ya cerrado.
    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto())
        ->assertStatus(422)
        ->assertJsonValidationErrors('estado');
});

it('marca el inicio de la visita con la ubicación', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    $this->actingAs($usuario)
        ->postJson(route('campo.iniciar', $visita), ['latitud' => 4.8133, 'longitud' => -75.6961])
        ->assertOk()
        ->assertJson(['estado' => OrdenTrabajo::ESTADO_EN_PROGRESO]);

    $visita->refresh();

    expect($visita->hora_inicio)->not->toBeNull()
        ->and((float) $visita->latitud_inicio)->toBe(4.8133);
});

it('no pisa la hora de inicio si la visita ya estaba en progreso', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $inicio = now()->subHour();

    $visita = visitaDePrueba($usuario, [
        'estado' => OrdenTrabajo::ESTADO_EN_PROGRESO,
        'hora_inicio' => $inicio,
    ]);

    $this->actingAs($usuario)->postJson(route('campo.iniciar', $visita))->assertOk();

    expect($visita->refresh()->hora_inicio->diffInMinutes($inicio, absolute: true))->toBeLessThan(2);
});
