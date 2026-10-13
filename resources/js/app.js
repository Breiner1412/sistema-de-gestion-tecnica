/**
 * Tema de la interfaz: claro, oscuro o el del sistema operativo.
 *
 * El primer pintado lo resuelve el script del <head> (partials/tema). Aquí
 * vive el cambio en caliente: el selector del menú aplica el tema al momento
 * y lo guarda en la cuenta, y con "sistema" se sigue al equipo si cambia.
 */

const consultaOscuro = window.matchMedia('(prefers-color-scheme: dark)')

function aplicarTema(tema) {
    const html = document.documentElement
    const oscuro = tema === 'oscuro' || (tema === 'sistema' && consultaOscuro.matches)

    html.dataset.tema = tema
    html.classList.toggle('dark', oscuro)
}

consultaOscuro.addEventListener('change', () => {
    if (document.documentElement.dataset.tema === 'sistema') {
        aplicarTema('sistema')
    }
})

function guardarTema(url, tema) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ tema }),
    }).catch(() => {
        /* Sin red se queda aplicado en esta página; se guardará la próxima vez. */
    })
}

function registrar(Alpine) {
    Alpine.data('selectorTema', (url) => ({
        tema: document.documentElement.dataset.tema || 'sistema',

        elegir(tema) {
            this.tema = tema
            aplicarTema(tema)
            guardarTema(url, tema)
        },
    }))
}

document.addEventListener('alpine:init', () => registrar(window.Alpine))

if (window.Alpine) {
    registrar(window.Alpine)
}

export { aplicarTema, guardarTema }
