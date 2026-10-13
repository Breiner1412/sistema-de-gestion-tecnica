import { test } from 'node:test'
import assert from 'node:assert/strict'

import { decidir } from '../../resources/js/campo/decision.js'

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
