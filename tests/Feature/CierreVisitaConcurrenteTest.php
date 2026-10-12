<?php

use App\Models\Inventario;
use App\Models\MaterialOrden;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Services\CierreDeVisita;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
 * Dos peticiones simultáneas cargan la misma orden antes de que ninguna la
 * cierre. Se reproduce de forma determinista con dos servicios que comparten
 * esa foto vieja de la orden: el segundo llega con el modelo desactualizado,
 * que es exactamente lo que ve una petición concurrente.
 */
beforeEach(fn () => Storage::fake('public'));

function materialConStock(int $cantidad): Inventario
{
    return Inventario::create([
        'nombre' => 'Conector SC/APC', 'tipo' => 'conector',
        'cantidad_total' => $cantidad,
    ]);
}

it('el mismo cierre enviado dos veces a la vez se aplica una sola vez', function () {
    $visita = visitaDePrueba();
    $material = materialConStock(10);
    $datos = cierreCompleto(['materiales' => [['material_id' => $material->id, 'cantidad' => 3]]]);

    $primera = CierreDeVisita::para(OrdenTrabajo::find($visita->id));
    $segunda = CierreDeVisita::para(OrdenTrabajo::find($visita->id)); // foto vieja

    $primera->registrar($datos);
    $resultado = $segunda->registrar($datos);

    expect($resultado['repetido'])->toBeTrue()
        ->and($material->refresh()->cantidad_total)->toBe(7)
        ->and(MaterialOrden::where('orden_id', $visita->id)->count())->toBe(1);
});

it('dos cierres distintos a la vez: el segundo se rechaza y no descuenta nada', function () {
    $visita = visitaDePrueba();
    $material = materialConStock(10);

    $primera = CierreDeVisita::para(OrdenTrabajo::find($visita->id));
    $segunda = CierreDeVisita::para(OrdenTrabajo::find($visita->id));

    $primera->registrar(cierreCompleto(['materiales' => [['material_id' => $material->id, 'cantidad' => 3]]]));

    expect(fn () => $segunda->registrar(cierreCompleto(['materiales' => [['material_id' => $material->id, 'cantidad' => 3]]])))
        ->toThrow(ValidationException::class);

    expect($material->refresh()->cantidad_total)->toBe(7)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('un uuid que ya usó otra visita se rechaza con un error de validación, no un 500', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $una = visitaDePrueba($usuario);
    $datos = cierreCompleto();

    $this->actingAs($usuario)->postJson(route('campo.cerrar', $una), $datos)->assertOk();

    $otra = OrdenTrabajo::create([...$una->only(['soporte_id', 'tecnico_id', 'tipo', 'franja']),
        'estado' => OrdenTrabajo::ESTADO_PROGRAMADA, 'fecha_programada' => today()]);

    $this->actingAs($usuario)->postJson(route('campo.cerrar', $otra), $datos)
        ->assertStatus(422)
        ->assertJsonValidationErrors('uuid');
});
