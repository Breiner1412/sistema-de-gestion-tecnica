/**
 * Cierre de visitas desde el celular, con o sin señal.
 *
 * El problema que resuelve este archivo: el técnico llega a un sótano, a una
 * zona rural o a un edificio con paredes gruesas, hace el trabajo y tiene que
 * cerrar la visita ahí mismo, delante del cliente que va a firmar. Si el cierre
 * depende de que haya internet en ese momento, el técnico lo apunta en un papel
 * y lo pasa al sistema por la noche —o no lo pasa—, que es exactamente el
 * agujero que este proyecto existe para tapar.
 *
 * Así que el cierre nunca depende de la red: se guarda en el teléfono y se
 * envía cuando se pueda. Las dos piezas delicadas son la identidad del envío
 * —el uuid lo pone el teléfono, para que un reenvío no cierre dos veces— y
 * distinguir el fallo que se arregla reintentando del que no.
 */

import { decidir, motivoPorDefecto } from './campo/decision.js'

/* ---------------------------------------------------------------
 | Cola en el teléfono
 * --------------------------------------------------------------- */

const BASE = 'sgt-campo'
const TIENDA = 'cierres'

function abrirBase() {
    return new Promise((cumplir, fallar) => {
        const peticion = indexedDB.open(BASE, 1)

        peticion.onupgradeneeded = () => {
            peticion.result.createObjectStore(TIENDA, { keyPath: 'uuid' })
        }
        peticion.onsuccess = () => cumplir(peticion.result)
        peticion.onerror = () => fallar(peticion.error)
    })
}

async function conLaTienda(modo, accion) {
    const base = await abrirBase()

    return new Promise((cumplir, fallar) => {
        const transaccion = base.transaction(TIENDA, modo)
        const peticion = accion(transaccion.objectStore(TIENDA))

        transaccion.oncomplete = () => cumplir(peticion ? peticion.result : undefined)
        transaccion.onerror = () => fallar(transaccion.error)
    })
}

const RECHAZADO = 'rechazado'

const cola = {
    guardar: (pendiente) => conLaTienda('readwrite', (t) => t.put(pendiente)),
    listar: () => conLaTienda('readonly', (t) => t.getAll()),
    borrar: (uuid) => conLaTienda('readwrite', (t) => t.delete(uuid)),
    obtener: (uuid) => conLaTienda('readonly', (t) => t.get(uuid)),

    /** Lo que todavía se intenta enviar. */
    async porEnviar() {
        return (await this.listar()).filter((p) => p.estado !== RECHAZADO)
    },

    /** Lo que el servidor no aceptó: se guarda hasta que alguien decida. */
    async rechazados() {
        return (await this.listar()).filter((p) => p.estado === RECHAZADO)
    },
}

/** Avisa a la barra de estado de cuántos quedan y de si algo se rechazó. */
async function anunciar(detalle = {}) {
    let pendientes = 0
    let rechazados = []

    try {
        pendientes = (await cola.porEnviar()).length
        rechazados = (await cola.rechazados()).map(({ uuid, error, creado }) => ({ uuid, error, creado }))
    } catch {
        /* sin IndexedDB no hay nada que contar */
    }

    window.dispatchEvent(new CustomEvent('campo:cola', { detail: { pendientes, rechazados, ...detalle } }))
}

/**
 * Deja el cierre guardado como rechazado, con el motivo, en vez de borrarlo:
 * la firma y las fotos no se pueden volver a tomar.
 */
async function marcarRechazado(pendiente) {
    await cola.guardar({ ...pendiente, estado: RECHAZADO })
}

/* ---------------------------------------------------------------
 | Envío y reintentos
 * --------------------------------------------------------------- */

function cabeceras() {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    }
}

/**
 * Un intento. Devuelve qué hacer con el pendiente (ver decision.js) y, si el
 * servidor lo rechazó, el motivo que dio.
 *
 * @returns {Promise<{decision: 'enviado'|'rechazado'|'reintentar'|'sesion', motivo: string|null}>}
 */
async function intentar(pendiente) {
    let respuesta

    try {
        respuesta = await fetch(pendiente.url, {
            method: 'POST',
            headers: cabeceras(),
            credentials: 'same-origin',
            body: JSON.stringify(pendiente.carga),
        })
    } catch {
        return { decision: decidir(null), motivo: null } // sin red, o se cayó a mitad
    }

    const decision = decidir(respuesta.status)

    if (decision !== 'rechazado') {
        return { decision, motivo: null }
    }

    let motivo = motivoPorDefecto(respuesta.status)

    try {
        const cuerpo = await respuesta.json()
        motivo = cuerpo.message || motivo
    } catch {
        /* sin cuerpo útil: un 413 del proxy, por ejemplo, viene en HTML */
    }

    return { decision, motivo }
}

let drenando = false

/** Vacía la cola. Se detiene al primer pendiente que haya que reintentar. */
async function drenar() {
    if (drenando || !navigator.onLine) {
        return
    }

    drenando = true

    try {
        for (const pendiente of await cola.porEnviar()) {
            const { decision, motivo } = await intentar(pendiente)

            if (decision === 'enviado') {
                await cola.borrar(pendiente.uuid)
                continue
            }

            // Un rechazo no frena a los demás: se aparta y se sigue.
            if (decision === 'rechazado') {
                await marcarRechazado({ ...pendiente, error: motivo })
                await anunciar({ error: motivo })
                continue
            }

            if (decision === 'sesion') {
                await anunciar({ error: 'La sesión se cerró. Entra otra vez para enviar lo que quedó guardado.' })
            }

            break // el resto espera: no tiene sentido insistir con la red caída
        }

        await anunciar()
    } finally {
        drenando = false
    }
}

/**
 * Encola y trata de enviar de una vez.
 *
 * Se guarda SIEMPRE antes de intentar el envío, incluso con señal: si el
 * navegador se cierra o el teléfono se queda sin batería a mitad del fetch,
 * el cierre ya está a salvo en el teléfono.
 */
async function encolar(url, carga) {
    const pendiente = { uuid: carga.uuid, url, carga, creado: new Date().toISOString() }

    await cola.guardar(pendiente)
    await anunciar()

    const { decision, motivo } = await intentar(pendiente)

    if (decision === 'enviado') {
        await cola.borrar(pendiente.uuid)
    }

    if (decision === 'rechazado') {
        await marcarRechazado({ ...pendiente, error: motivo })
    }

    await anunciar({ error: motivo })

    return { decision, motivo }
}

/** Baja el cierre rechazado como archivo, con fotos y firma, antes de descartarlo. */
async function descargarCierre(uuid) {
    const pendiente = await cola.obtener(uuid)

    if (!pendiente) {
        return
    }

    const archivo = new Blob([JSON.stringify(pendiente, null, 2)], { type: 'application/json' })
    const enlace = document.createElement('a')
    enlace.href = URL.createObjectURL(archivo)
    enlace.download = `cierre-${uuid}.json`
    enlace.click()
    URL.revokeObjectURL(enlace.href)
}

async function descartarCierre(uuid) {
    await cola.borrar(uuid)
    await anunciar()
}

window.addEventListener('online', drenar)
window.addEventListener('load', drenar)
setInterval(drenar, 60_000)

/* ---------------------------------------------------------------
 | Fotos
 * --------------------------------------------------------------- */

/**
 * Reduce la foto antes de guardarla.
 *
 * Una foto de celular son 4 MB. Seis de esas en la cola llenan el
 * almacenamiento del navegador y tardan una eternidad en subir por datos
 * móviles. A 1280 px y calidad 0,7 una toma de un tablero o de un cable roto
 * se sigue viendo perfectamente y pesa unos 200 KB.
 */
async function reducirFoto(archivo, ladoMaximo = 1280, calidad = 0.7) {
    const imagen = await createImageBitmap(archivo)
    const escala = Math.min(1, ladoMaximo / Math.max(imagen.width, imagen.height))

    const lienzo = document.createElement('canvas')
    lienzo.width = Math.round(imagen.width * escala)
    lienzo.height = Math.round(imagen.height * escala)
    lienzo.getContext('2d').drawImage(imagen, 0, 0, lienzo.width, lienzo.height)

    imagen.close?.()

    return lienzo.toDataURL('image/jpeg', calidad)
}

function ubicacion() {
    return new Promise((cumplir) => {
        if (!navigator.geolocation) {
            return cumplir(null)
        }

        navigator.geolocation.getCurrentPosition(
            (posicion) => cumplir({
                latitud: +posicion.coords.latitude.toFixed(7),
                longitud: +posicion.coords.longitude.toFixed(7),
            }),
            // Sin GPS se cierra igual: la ubicación es evidencia, no requisito.
            () => cumplir(null),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60_000 },
        )
    })
}

/* ---------------------------------------------------------------
 | Componentes de Alpine
 * --------------------------------------------------------------- */

function registrar(Alpine) {
    Alpine.data('estadoDeConexion', () => ({
        sinSenal: !navigator.onLine,
        pendientes: 0,
        rechazados: [],
        verRechazados: false,
        ultimoError: '',

        init() {
            window.addEventListener('online', () => (this.sinSenal = false))
            window.addEventListener('offline', () => (this.sinSenal = true))

            window.addEventListener('campo:cola', ({ detail }) => {
                if (typeof detail.pendientes === 'number') {
                    this.pendientes = detail.pendientes
                }
                if (Array.isArray(detail.rechazados)) {
                    this.rechazados = detail.rechazados
                }
                if (detail.error) {
                    this.ultimoError = detail.error
                    setTimeout(() => (this.ultimoError = ''), 12_000)
                }
            })

            anunciar()
        },

        descargar(uuid) {
            descargarCierre(uuid)
        },

        async descartar(uuid) {
            if (confirm('Se borra del teléfono con su firma y sus fotos. ¿Ya lo descargaste o lo volviste a cerrar?')) {
                await descartarCierre(uuid)
            }
        },
    }))

    Alpine.data('cierreDeVisita', (config) => ({
        ...config,

        estado: 'completado',
        diagnosticoId: '',
        observaciones: '',
        motivo: '',
        fotos: [],
        firma: null,
        materiales: [],
        materialElegido: '',
        cantidad: 1,
        gps: null,
        enviando: false,
        error: '',
        listo: false,
        guardadoSinSenal: false,
        lienzoFirma: null,

        init() {
            // Se pide la ubicación al abrir, no al cerrar: el GPS tarda unos
            // segundos en fijar y para entonces ya está lista.
            ubicacion().then((u) => (this.gps = u))

            if (!this.yaIniciada) {
                this.marcarInicio()
            }
        },

        async marcarInicio() {
            const posicion = this.gps ?? (await ubicacion())

            // Si falla no pasa nada: el cierre pone la hora de inicio.
            fetch(this.urlInicio, {
                method: 'POST',
                headers: cabeceras(),
                credentials: 'same-origin',
                body: JSON.stringify(posicion ?? {}),
            }).catch(() => {})
        },

        /* --- Fotos --- */

        async agregarFoto(evento) {
            const archivos = [...evento.target.files].slice(0, 6 - this.fotos.length)

            for (const archivo of archivos) {
                try {
                    this.fotos.push({
                        contenido: await reducirFoto(archivo),
                        tomada_at: new Date().toISOString(),
                        descripcion: null,
                    })
                } catch {
                    this.error = 'No se pudo leer una de las fotos.'
                }
            }

            evento.target.value = ''
        },

        quitarFoto(indice) {
            this.fotos.splice(indice, 1)
        },

        /* --- Firma --- */

        prepararFirma(lienzo) {
            const contexto = lienzo.getContext('2d')
            let trazando = false

            // El lienzo se dimensiona en píxeles reales del dispositivo: a
            // media resolución la firma sale con los bordes dentados.
            const escala = window.devicePixelRatio || 1
            lienzo.width = lienzo.offsetWidth * escala
            lienzo.height = lienzo.offsetHeight * escala
            contexto.scale(escala, escala)
            contexto.lineWidth = 2
            contexto.lineCap = 'round'
            contexto.lineJoin = 'round'
            contexto.strokeStyle = '#0f172a'

            const punto = (evento) => {
                const caja = lienzo.getBoundingClientRect()

                return [evento.clientX - caja.left, evento.clientY - caja.top]
            }

            lienzo.addEventListener('pointerdown', (evento) => {
                evento.preventDefault()
                trazando = true
                lienzo.setPointerCapture(evento.pointerId)
                contexto.beginPath()
                contexto.moveTo(...punto(evento))
            })

            lienzo.addEventListener('pointermove', (evento) => {
                if (!trazando) return
                evento.preventDefault()
                contexto.lineTo(...punto(evento))
                contexto.stroke()
            })

            const soltar = () => {
                if (!trazando) return
                trazando = false
                this.firma = lienzo.toDataURL('image/png')
            }

            lienzo.addEventListener('pointerup', soltar)
            lienzo.addEventListener('pointerleave', soltar)

            this.lienzoFirma = lienzo
        },

        borrarFirma() {
            const lienzo = this.lienzoFirma
            lienzo.getContext('2d').clearRect(0, 0, lienzo.width, lienzo.height)
            this.firma = null
        },

        /* --- Materiales --- */

        agregarMaterial() {
            const material = this.catalogo.find((m) => m.id === +this.materialElegido)

            if (!material || this.cantidad < 1) {
                return
            }

            this.materiales.push({
                material_id: material.id,
                cantidad: +this.cantidad,
                nombre: material.nombre,
            })

            this.materialElegido = ''
            this.cantidad = 1
        },

        quitarMaterial(indice) {
            this.materiales.splice(indice, 1)
        },

        /* --- Envío --- */

        get completa() {
            return this.estado === 'completado'
        },

        get puedeEnviar() {
            if (this.enviando) return false

            return this.completa
                ? this.diagnosticoId !== '' && this.observaciones.trim().length >= 10
                : this.motivo.trim().length >= 5
        },

        async enviar() {
            if (!this.puedeEnviar) return

            this.enviando = true
            this.error = ''

            const carga = {
                uuid: crypto.randomUUID(),
                estado: this.estado,
                diagnostico_id: this.completa ? +this.diagnosticoId : null,
                observaciones: this.completa ? this.observaciones.trim() : null,
                motivo: this.completa ? null : this.motivo.trim(),
                cerrada_en_terreno_at: new Date().toISOString(),
                latitud: this.gps?.latitud ?? null,
                longitud: this.gps?.longitud ?? null,
                firma: this.completa ? this.firma : null,
                fotos: this.fotos,
                materiales: this.materiales.map(({ material_id, cantidad }) => ({ material_id, cantidad })),
            }

            const { decision, motivo } = await encolar(this.urlCierre, carga)

            if (decision === 'rechazado') {
                this.enviando = false
                this.error = `${motivo} Quedó guardado en el teléfono: corrige y vuelve a cerrar, o descárgalo desde la barra de arriba.`

                return
            }

            // Enviado o guardado: en los dos casos el técnico ya terminó aquí.
            this.listo = true
            this.guardadoSinSenal = decision !== 'enviado'

            setTimeout(() => (window.location.href = this.urlRuta), 1600)
        },
    }))
}

document.addEventListener('alpine:init', () => registrar(window.Alpine))

// Livewire arranca Alpine por su cuenta; si este módulo llega tarde, el evento
// ya pasó y hay que registrarse a mano.
if (window.Alpine) {
    registrar(window.Alpine)
}
