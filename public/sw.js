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
 *  - las páginas se piden primero a la red y solo se sirve la copia guardada
 *    si la red falla, para que nunca se vea una ruta de ayer estando en línea;
 *  - los archivos con huella (los de Vite) se sirven de caché, que para eso
 *    llevan la huella en el nombre.
 */

const CACHE = 'sgt-campo-v1'

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

self.addEventListener('install', (evento) => {
    evento.waitUntil(self.skipWaiting())
})

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches
            .keys()
            .then((nombres) => Promise.all(nombres.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
            .then(() => self.clients.claim()),
    )
})

self.addEventListener('fetch', (evento) => {
    const peticion = evento.request
    const url = new URL(peticion.url)

    if (peticion.method !== 'GET' || url.origin !== self.location.origin) {
        return
    }

    // Las páginas: red primero, copia guardada como red de seguridad.
    if (peticion.mode === 'navigate') {
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

    // Recursos: caché primero, y de paso se refresca en segundo plano.
    if (/\/(build|iconos|storage)\//.test(url.pathname)) {
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
    // Las respuestas parciales o de error no sirven de copia de seguridad.
    if (!respuesta.ok || respuesta.status === 206) {
        return
    }

    caches.open(CACHE).then((cache) => cache.put(peticion, respuesta))
}
