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

const cola = {
    guardar: (pendiente) => conLaTienda('readwrite', (t) => t.put(pendiente)),
    listar: () => conLaTienda('readonly', (t) => t.getAll()),
    borrar: (uuid) => conLaTienda('readwrite', (t) => t.delete(uuid)),
    async contar() {
        try {
            return (await this.listar()).length
        } catch {
            return 0
        }
    },
}

function anunciar(detalle) {
    window.dispatchEvent(new CustomEvent('campo:cola', { detail: detalle }))
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
 * Un intento. Devuelve qué hacer con el pendiente, que es la decisión que
 * importa: reintentar a ciegas un cierre que el servidor rechazó por datos
 * inválidos deja la cola atascada para siempre.
 *
 * @returns {'enviado'|'descartar'|'reintentar'|'sesion'}
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
        return 'reintentar' // sin red, o se cayó a mitad
    }

    if (respuesta.ok) {
        return 'enviado'
    }

    // 419: la sesión caducó mientras el cierre esperaba en el teléfono. Los
    // datos están bien; lo que falta es que la persona vuelva a entrar.
    if (respuesta.status === 419 || respuesta.status === 401) {
        return 'sesion'
    }

    // 422 y 403 no mejoran reintentando: o los datos no pasan la validación o
    // esta visita no es de quien la envía.
    if (respuesta.status === 422 || respuesta.status === 403 || respuesta.status === 404) {
        let detalle = 'El servidor rechazó un cierre guardado.'

        try {
            const cuerpo = await respuesta.json()
            detalle = cuerpo.message || detalle
        } catch {
            /* sin cuerpo útil */
        }

        pendiente.error = detalle

        return 'descartar'
    }

    return 'reintentar' // 5xx y demás: puede ser pasajero
}

let drenando = false

/** Vacía la cola. Se detiene al primer pendiente que haya que reintentar. */
async function drenar() {
    if (drenando || !navigator.onLine) {
        return
    }

    drenando = true

    try {
        for (const pendiente of await cola.listar()) {
            const decision = await intentar(pendiente)

            if (decision === 'enviado') {
                await cola.borrar(pendiente.uuid)
                continue
            }

            if (decision === 'descartar') {
                await cola.borrar(pendiente.uuid)
                anunciar({ error: pendiente.error })
                continue
            }

            if (decision === 'sesion') {
                anunciar({ error: 'La sesión se cerró. Entra otra vez para enviar lo que quedó guardado.' })
            }

            break // el resto espera: no tiene sentido insistir con la red caída
        }

        anunciar({ pendientes: await cola.contar() })
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
    anunciar({ pendientes: await cola.contar() })

    const decision = await intentar(pendiente)

    if (decision === 'enviado' || decision === 'descartar') {
        await cola.borrar(pendiente.uuid)
    }

    anunciar({ pendientes: await cola.contar(), error: decision === 'descartar' ? pendiente.error : null })

    return decision
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
        ultimoError: '',

        init() {
            window.addEventListener('online', () => (this.sinSenal = false))
            window.addEventListener('offline', () => (this.sinSenal = true))

            window.addEventListener('campo:cola', ({ detail }) => {
                if (typeof detail.pendientes === 'number') {
                    this.pendientes = detail.pendientes
                }
                if (detail.error) {
                    this.ultimoError = detail.error
                    setTimeout(() => (this.ultimoError = ''), 12_000)
                }
            })

            cola.contar().then((n) => (this.pendientes = n))
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

            const decision = await encolar(this.urlCierre, carga)

            if (decision === 'descartar') {
                this.enviando = false
                this.error = 'El servidor rechazó el cierre. Revisa los datos.'

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
