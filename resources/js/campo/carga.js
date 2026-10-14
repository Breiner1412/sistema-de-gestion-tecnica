/**
 * Arma el cierre que se guarda en el teléfono y se envía al servidor.
 *
 * El formulario vive en Alpine, que envuelve arreglos y objetos en proxies
 * reactivos, y IndexedDB no puede clonar un Proxy: guardarlo tal cual fallaba
 * siempre, con o sin señal. Aquí todo sale como copia simple, campo por campo.
 */
export function armarCarga(formulario, uuid, cerradaEn) {
    const completa = formulario.estado === 'completado'

    return {
        uuid,
        estado: formulario.estado,
        diagnostico_id: completa ? +formulario.diagnosticoId : null,
        observaciones: completa ? formulario.observaciones.trim() : null,
        motivo: completa ? null : formulario.motivo.trim(),
        cerrada_en_terreno_at: cerradaEn,
        latitud: formulario.gps?.latitud ?? null,
        longitud: formulario.gps?.longitud ?? null,
        firma: completa ? formulario.firma : null,
        fotos: [...formulario.fotos].map(({ contenido, tomada_at, descripcion }) => ({ contenido, tomada_at, descripcion })),
        materiales: [...formulario.materiales].map(({ material_id, cantidad }) => ({ material_id, cantidad })),
    }
}
