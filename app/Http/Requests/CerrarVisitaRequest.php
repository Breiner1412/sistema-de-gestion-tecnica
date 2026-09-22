<?php

namespace App\Http\Requests;

use App\Models\OrdenTrabajo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CerrarVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        $orden = $this->route('orden');

        if (! $usuario || ! $orden instanceof OrdenTrabajo) {
            return false;
        }

        return $usuario->tieneRol(...User::ROLES_GESTION)
            || $usuario->tecnico?->id === $orden->tecnico_id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Lo pone el teléfono antes de enviar: es lo que permite reconocer
            // un reenvío después de una sincronización a medias.
            'uuid' => ['required', 'uuid'],

            'estado' => ['required', Rule::in([
                OrdenTrabajo::ESTADO_COMPLETADO,
                OrdenTrabajo::ESTADO_NO_REALIZADA,
            ])],

            'diagnostico_id' => [
                Rule::requiredIf(fn() => $this->input('estado') === OrdenTrabajo::ESTADO_COMPLETADO),
                'nullable', 'integer', 'exists:diagnosticos,id',
            ],
            'observaciones' => [
                Rule::requiredIf(fn() => $this->input('estado') === OrdenTrabajo::ESTADO_COMPLETADO),
                'nullable', 'string', 'min:10', 'max:2000',
            ],
            'motivo' => [
                Rule::requiredIf(fn() => $this->input('estado') === OrdenTrabajo::ESTADO_NO_REALIZADA),
                'nullable', 'string', 'min:5', 'max:500',
            ],

            'cerrada_en_terreno_at' => ['nullable', 'date'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],

            // Las imágenes llegan en base64 dentro del JSON porque salen de una
            // cola guardada en el teléfono, no de un <input type=file>. El tope
            // es generoso: el navegador ya las reduce antes de encolarlas.
            'firma' => ['nullable', 'string', 'max:2000000'],
            'fotos' => ['nullable', 'array', 'max:6'],
            'fotos.*.contenido' => ['required', 'string', 'max:4000000'],
            'fotos.*.descripcion' => ['nullable', 'string', 'max:120'],
            'fotos.*.tomada_at' => ['nullable', 'date'],

            'materiales' => ['nullable', 'array', 'max:20'],
            'materiales.*.material_id' => ['required', 'integer', 'exists:inventario,id'],
            'materiales.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'diagnostico_id.required' => 'Selecciona el diagnóstico antes de cerrar la visita.',
            'observaciones.required' => 'Describe el trabajo realizado antes de cerrar la visita.',
            'observaciones.min' => 'La descripción del trabajo es muy corta.',
            'motivo.required' => 'Explica por qué no se pudo hacer la visita.',
            'fotos.max' => 'Máximo seis fotos por visita.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'diagnostico_id' => 'diagnóstico',
            'observaciones' => 'trabajo realizado',
            'motivo' => 'motivo',
        ];
    }
}
