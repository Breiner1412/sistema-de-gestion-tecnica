/**
 * Qué hacer con un cierre guardado según lo que respondió el servidor.
 *
 * Es la decisión que más importa de la cola: un cierre lleva la firma del
 * cliente y las fotos de la visita, que no se pueden volver a tomar. Por eso
 * nada que no sea un "guardado" del servidor lo borra del teléfono.
 *
 *  - enviado:    2xx. El servidor lo tiene; se puede borrar del teléfono.
 *  - sesion:     401/419. Los datos están bien; falta volver a entrar.
 *  - rechazado:  el servidor no lo acepta tal como está (datos inválidos, la
 *                visita no es de quien envía, ya no existe). Reintentar no lo
 *                arregla, pero tampoco se borra: queda guardado y a la vista
 *                para que el técnico lo descargue o lo descarte a conciencia.
 *  - reintentar: sin red, 5xx o un límite de peticiones. Puede ser pasajero.
 *
 * @param {number|null} estado  código HTTP, o null si no hubo respuesta
 * @returns {'enviado'|'sesion'|'rechazado'|'reintentar'}
 */
export function decidir(estado) {
    if (estado === null) {
        return 'reintentar'
    }

    if (estado >= 200 && estado < 300) {
        return 'enviado'
    }

    if (estado === 401 || estado === 419) {
        return 'sesion'
    }

    if (estado === 422 || estado === 403 || estado === 404) {
        return 'rechazado'
    }

    return 'reintentar'
}
