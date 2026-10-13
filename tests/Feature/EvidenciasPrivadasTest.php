<?php

use App\Models\Evidencia;
use App\Models\Tecnico;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

/** Cierra una visita con foto y firma y devuelve [visita, técnico dueño]. */
function visitaConEvidencias(): array
{
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);

    test()->actingAs($usuario)->postJson(route('campo.cerrar', $visita), cierreCompleto([
        'firma' => 'data:image/png;base64,'.PNG_MINIMO,
        'fotos' => [['contenido' => fotoDePrueba()]],
    ]))->assertOk();

    return [$visita->refresh(), $usuario];
}

it('las fotos y la firma quedan en el disco privado, no en el público', function () {
    Storage::fake('public');
    [$visita] = visitaConEvidencias();

    expect(Storage::disk('local')->exists($visita->firma_path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($visita->urlFirma())->not->toContain('/storage/');
});

it('el dueño de la visita ve la foto y la firma', function () {
    [$visita, $usuario] = visitaConEvidencias();
    $foto = Evidencia::where('orden_id', $visita->id)->firstOrFail();

    $this->actingAs($usuario)->get($foto->url())->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($usuario)->get($visita->urlFirma())->assertOk();
});

it('otro técnico de campo no ve las fotos ni la firma de una visita ajena', function () {
    [$visita] = visitaConEvidencias();
    $foto = Evidencia::where('orden_id', $visita->id)->firstOrFail();

    $otro = usuarioCon(User::ROL_TECNICO_CAMPO);
    Tecnico::create(['user_id' => $otro->id, 'nombre' => $otro->name, 'estado' => 'activo']);

    $this->actingAs($otro)->get($foto->url())->assertForbidden();
    $this->actingAs($otro)->get($visita->urlFirma())->assertForbidden();
});

it('sin sesión no se ve nada', function () {
    [$visita] = visitaConEvidencias();
    auth()->logout();

    $this->get($visita->urlFirma())->assertRedirect(route('login'));
});

it('rechaza como foto algo que no es una imagen', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);
    $html = 'data:image/jpeg;base64,'.base64_encode('<html><script>alert(1)</script></html>');

    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto(['fotos' => [['contenido' => $html]]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('fotos');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('guarda la foto redibujada, sin los metadatos del original', function () {
    [$visita] = visitaConEvidencias();
    $foto = Evidencia::where('orden_id', $visita->id)->firstOrFail();

    $guardada = Storage::disk('local')->get($foto->archivo_url);

    expect(getimagesizefromstring($guardada)['mime'])->toBe('image/jpeg')
        ->and($guardada)->not->toBe(base64_decode(JPEG_MINIMO));
})->skip(fn () => ! function_exists('imagecreatefromstring'), 'Sin GD no se redibuja');
