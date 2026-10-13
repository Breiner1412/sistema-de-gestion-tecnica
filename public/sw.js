/**
 * Service worker de la pantalla de campo.
 *
 * Su único trabajo es que la pantalla ABRA sin señal. El envío del cierre no
 * pasa por aquí: eso lo guarda campo.js en IndexedDB y lo reintenta cuando
 * vuelve la red. Separarlo así evita el error clásico de estos montajes, que
 * es un service worker respondiendo desde caché algo que el usuario acaba de
 * cambiar.
 *
 * Reglas:
 *  - solo GET del mismo origen; todo lo demás va derecho a la red;
 *  - solo se guardan las páginas de /campo. Antes se guardaba cualquier
 *    página que se abriera (clientes, usuarios, órdenes de otros), y quedaban
 *    en el teléfono después de cerrar sesión;
 *  - las páginas se piden primero a la red y solo se sirve la copia guardada
 *    si la red falla, para que nunca se vea una ruta de ayer estando en línea;
 *  - una redirección no se guarda: con la sesión caducada, /campo redirige al
 *    login y esa respuesta pisaba la copia buena de la ruta;
 *  - los archivos con huella (los de Vite) y los íconos se sirven de caché;
 *    las fotos y firmas no, porque son privadas y van por ruta autenticada;
 *  - al cerrar sesión, la página de entrada borra estas copias (ver
 *    limpiarCopiasDeCampo en layouts/guest y welcome).
 */

const CACHE = 'sgt-campo-v2'

const RESPALDO = `<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sin señal</title>
<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#0f172a;
display:grid;place-items:center;height:100vh;margin:0;text-align:center;padding:24px}</style>
</head><body><div>
<h1 style="font-size:1.1rem">Sin señal</h1>
<p style="color:#475569;max-width:22rem">Esta pantalla no se había abierto antes en este
teléfono, así que no hay copia guardada. Vuelve a intentarlo cuando tengas señal.</p>
</div></body></html>`

const esDeCampo = (url) => url.pathname === '/campo' || url.pathname.startsWith('/campo/')

const esRecursoConHuella = (url) => /^\/(build|iconos)\//.test(url.pathname)

self.addEventListener('install', (evento) => {
    evento.waitUntil(self.skipWaiting())
})

// Al activarse borra las versiones anteriores: la v1 guardaba páginas de toda
// la aplicación y las fotos de /storage.
self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches
            .keys()
            .then((nombres) => Promise.all(nombres.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
            .then(() => self.clients.claim()),
    )
})

self.addEventListener('message', (evento) => {
    if (evento.data?.tipo === 'limpiar') {
        evento.waitUntil(caches.delete(CACHE))
    }
})

self.addEventListener('fetch', (evento) => {
    const peticion = evento.request
    const url = new URL(peticion.url)

    if (peticion.method !== 'GET' || url.origin !== self.location.origin) {
        return
    }

    // Las páginas de campo: red primero, copia guardada como red de seguridad.
    if (peticion.mode === 'navigate') {
        if (!esDeCampo(url)) {
            return
        }

        evento.respondWith(
            fetch(peticion)
                .then((respuesta) => {
                    guardar(peticion, respuesta.clone())

                    return respuesta
                })
                .catch(async () => {
                    const guardada = await caches.match(peticion)

                    return (
                        guardada ??
                        new Response(RESPALDO, { headers: { 'Content-Type': 'text/html; charset=utf-8' } })
                    )
                }),
        )

        return
    }

    // Recursos con huella: caché primero, y de paso se refresca en segundo plano.
    if (esRecursoConHuella(url)) {
        evento.respondWith(
            caches.match(peticion).then((guardada) => {
                const desdeLaRed = fetch(peticion)
                    .then((respuesta) => {
                        guardar(peticion, respuesta.clone())

                        return respuesta
                    })
                    .catch(() => guardada)

                return guardada ?? desdeLaRed
            }),
        )
    }
})

function guardar(peticion, respuesta) {
    // Las respuestas parciales, de error o redirigidas no sirven de copia.
    if (!respuesta.ok || respuesta.status === 206 || respuesta.redirected) {
        return
    }

    caches.open(CACHE).then((cache) => cache.put(peticion, respuesta))
}
