import { test } from 'node:test'
import assert from 'node:assert/strict'

import { decidir, esDe, motivoPorDefecto } from '../../resources/js/campo/decision.js'

test('un 2xx es lo único que permite borrar el cierre del teléfono', () => {
    assert.equal(decidir(200), 'enviado')
    assert.equal(decidir(201), 'enviado')

    for (const estado of [301, 400, 401, 403, 404, 413, 419, 422, 429, 500, 503, null]) {
        assert.notEqual(decidir(estado), 'enviado', `con ${estado} el cierre no puede borrarse`)
    }
})

test('sin red o con el servidor caído se reintenta', () => {
    assert.equal(decidir(null), 'reintentar')
    assert.equal(decidir(500), 'reintentar')
    assert.equal(decidir(503), 'reintentar')
})

test('una sesión caducada no es un rechazo: los datos se conservan', () => {
    assert.equal(decidir(401), 'sesion')
    assert.equal(decidir(419), 'sesion')
})

test('un rechazo del servidor se conserva como rechazado, no se descarta', () => {
    assert.equal(decidir(422), 'rechazado')
    assert.equal(decidir(403), 'rechazado')
    assert.equal(decidir(404), 'rechazado')
})

test('un cierre demasiado pesado se aparta en vez de atascar la cola', () => {
    assert.equal(decidir(413), 'rechazado')
    assert.match(motivoPorDefecto(413), /pesa demasiado/)
})

test('los demás 4xx tampoco se reintentan para siempre', () => {
    for (const estado of [400, 405, 409, 410, 415]) {
        assert.equal(decidir(estado), 'rechazado', `${estado}`)
    }
})

test('tiempo agotado y demasiadas peticiones sí se reintentan', () => {
    assert.equal(decidir(408), 'reintentar')
    assert.equal(decidir(429), 'reintentar')
})

test('la cola solo envía los cierres de quien tiene la sesión abierta', () => {
    assert.equal(esDe({ usuarioId: '7' }, '7'), true)
    assert.equal(esDe({ usuarioId: 7 }, '7'), true)
    assert.equal(esDe({ usuarioId: '7' }, '9'), false, 'el cierre de otro técnico espera a su dueño')
    assert.equal(esDe({ usuarioId: '7' }, null), false)
})

test('un cierre guardado antes de que existiera el dueño se envía con la sesión actual', () => {
    assert.equal(esDe({}, '9'), true)
})
