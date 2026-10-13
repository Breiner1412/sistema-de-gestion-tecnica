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

    // Tiempo agotado o demasiadas peticiones: el mismo envío pasa más tarde.
    if (estado === 408 || estado === 429) {
        return 'reintentar'
    }

    // El resto de 4xx no mejora reintentando. Antes solo 403, 404 y 422 se
    // apartaban: un 413 (cierre más pesado de lo que acepta el servidor) se
    // reintentaba para siempre y dejaba atascada toda la cola detrás.
    if (estado >= 400 && estado < 500) {
        return 'rechazado'
    }

    return 'reintentar'
}

/**
 * Motivo para mostrar cuando el servidor no mandó uno (un 413 del proxy, por
 * ejemplo, responde HTML y no JSON).
 *
 * @param {number} estado
 * @returns {string}
 */
export function motivoPorDefecto(estado) {
    if (estado === 413) {
        return 'El cierre pesa demasiado para enviarlo. Quita algunas fotos y vuelve a cerrarlo.'
    }

    if (estado === 403) {
        return 'Esta visita ya no está a tu nombre.'
    }

    if (estado === 404) {
        return 'Esta visita ya no existe.'
    }

    return 'El servidor rechazó este cierre.'
}
