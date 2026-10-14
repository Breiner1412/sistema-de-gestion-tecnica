import { test } from 'node:test'
import assert from 'node:assert/strict'

import { armarCarga } from '../../resources/js/campo/carga.js'

// Alpine envuelve el estado del formulario en proxies reactivos. IndexedDB no
// puede clonar un Proxy: el cierre fallaba al guardarse en el teléfono, con o
// sin señal, y el botón se quedaba en "Guardando…".
const reactivo = (valor) => new Proxy(valor, {})

const formulario = (cambios = {}) => ({
    estado: 'completado',
    diagnosticoId: '4',
    observaciones: '  Cambio de conector en la acometida.  ',
    motivo: '',
    gps: reactivo({ latitud: 4.81, longitud: -75.69 }),
    firma: 'data:image/png;base64,AAAA',
    fotos: reactivo([reactivo({ contenido: 'data:image/jpeg;base64,BBBB', tomada_at: '2026-10-14T10:05:00.000Z', descripcion: null })]),
    materiales: reactivo([reactivo({ material_id: 7, nombre: 'Conector SC/APC', cantidad: 2 })]),
    ...cambios,
})

test('la carga se puede guardar en IndexedDB aunque el formulario sea reactivo', () => {
    const carga = armarCarga(formulario(), 'uuid-1', '2026-10-14T10:06:00.000Z')

    assert.doesNotThrow(() => structuredClone(carga))
})

test('una visita resuelta lleva diagnóstico, trabajo, firma, fotos y material', () => {
    const carga = armarCarga(formulario(), 'uuid-1', '2026-10-14T10:06:00.000Z')

    assert.deepEqual(carga, {
        uuid: 'uuid-1',
        estado: 'completado',
        diagnostico_id: 4,
        observaciones: 'Cambio de conector en la acometida.',
        motivo: null,
        cerrada_en_terreno_at: '2026-10-14T10:06:00.000Z',
        latitud: 4.81,
        longitud: -75.69,
        firma: 'data:image/png;base64,AAAA',
        fotos: [{ contenido: 'data:image/jpeg;base64,BBBB', tomada_at: '2026-10-14T10:05:00.000Z', descripcion: null }],
        materiales: [{ material_id: 7, cantidad: 2 }],
    })
})

test('una visita que no se pudo hacer lleva el motivo y no la firma ni el diagnóstico', () => {
    const carga = armarCarga(formulario({ estado: 'no_realizada', motivo: ' No había nadie. ', gps: null }), 'uuid-2', '2026-10-14T11:00:00.000Z')

    assert.equal(carga.diagnostico_id, null)
    assert.equal(carga.observaciones, null)
    assert.equal(carga.motivo, 'No había nadie.')
    assert.equal(carga.firma, null)
    assert.equal(carga.latitud, null)
    assert.doesNotThrow(() => structuredClone(carga))
})
