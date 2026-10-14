/**
 * Capturas de pantalla para el README y el manual de usuario.
 *
 * Entra con las cuentas demo contra una instalación con los datos ficticios de
 * `php artisan demo:reiniciar` y guarda las imágenes en docs/capturas/. Usa el
 * Chrome instalado en el equipo (no descarga navegadores).
 *
 *   CAPTURAS_URL=http://127.0.0.1:8000 DEMO_PASSWORD=... \
 *   ADMIN_EMAIL=... ADMIN_PASSWORD=... npm run capturas
 *
 * Las claves se leen del entorno y nunca aparecen en las imágenes: la de la
 * demo, que el login muestra, se tapa al capturar.
 */
import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const BASE = (process.env.CAPTURAS_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '')
const CLAVE_DEMO = process.env.DEMO_PASSWORD
const ADMIN = { correo: process.env.ADMIN_EMAIL, clave: process.env.ADMIN_PASSWORD }
const SALIDA = fileURLToPath(new URL('../docs/capturas/', import.meta.url))

if (!CLAVE_DEMO || !ADMIN.correo || !ADMIN.clave) {
    console.error('Faltan DEMO_PASSWORD, ADMIN_EMAIL o ADMIN_PASSWORD en el entorno.')
    process.exit(1)
}

// Cuentas semilla de la demo (datos ficticios).
const CUENTAS = {
    gerencia: 'gerencia@sgt.local',
    soporte: 'andres.arango@sgt.local',
    campo: 'campo1@sgt.local',
}

const ESCRITORIO = { width: 1440, height: 900 }
const CELULAR = { width: 390, height: 844 }

mkdirSync(SALIDA, { recursive: true })

const navegador = await chromium.launch({ channel: 'chrome' })
const hechas = []

/** Abre un contexto con sesión iniciada, en el tamaño y el esquema pedidos. */
async function sesion(correo, clave, { viewport = ESCRITORIO, esquema = 'light' } = {}) {
    const contexto = await navegador.newContext({ viewport, colorScheme: esquema, locale: 'es-CO' })
    const pagina = await contexto.newPage()

    if (correo) {
        await pagina.goto(`${BASE}/login`)
        await pagina.fill('#email', correo)
        await pagina.fill('#password', clave)
        await Promise.all([pagina.waitForURL((url) => !url.pathname.startsWith('/login')), pagina.click('form button[type="submit"]')])
    }

    return { contexto, pagina }
}

async function capturar(pagina, nombre, { completa = false, tapar = [], preparar = null } = {}) {
    await pagina.waitForLoadState('networkidle')
    await pagina.evaluate(() => document.fonts.ready)

    if (preparar) {
        await preparar(pagina)
    }

    // Página completa estirando la ventana, no con fullPage: así el menú
    // lateral, que mide lo que mide la ventana, llega hasta abajo.
    if (completa) {
        const alto = await pagina.evaluate(() => document.documentElement.scrollHeight)
        await pagina.setViewportSize({ width: pagina.viewportSize().width, height: alto })
    }

    await pagina.screenshot({
        path: SALIDA + nombre + '.png',
        mask: tapar.map((selector) => pagina.locator(selector)),
        maskColor: '#C9D0D8',
        animations: 'disabled',
    })
    hechas.push(nombre)
    console.log('✓', nombre)
}

/** Una pantalla en uno o más esquemas de color. */
async function pantalla(nombre, ruta, correo, clave, { esquemas = ['light'], viewport = ESCRITORIO, completa = false, tapar = [], preparar = null } = {}) {
    for (const esquema of esquemas) {
        const { contexto, pagina } = await sesion(correo, clave, { viewport, esquema })
        await pagina.goto(BASE + (typeof ruta === 'function' ? await ruta(pagina) : ruta))
        const sufijo = viewport === CELULAR ? 'celular' : 'escritorio'
        await capturar(pagina, `${nombre}-${sufijo}-${esquema === 'dark' ? 'oscuro' : 'claro'}`, { completa, tapar, preparar })
        await contexto.close()
    }
}

/** Primer caso en proceso del técnico demo que no esté vencido: la ficha más representativa. */
async function rutaFicha(pagina) {
    await pagina.goto(`${BASE}/soportes?vista=mios&estado=en_proceso`)
    const filas = pagina.locator('tbody tr')
    const total = await filas.count()

    for (let i = 0; i < total; i++) {
        const fila = filas.nth(i)
        if ((await fila.innerText()).includes('Vencido')) continue
        return new URL(await fila.locator('a').first().getAttribute('href')).pathname
    }

    return new URL(await filas.first().locator('a').first().getAttribute('href')).pathname
}

/** El informe del mes anterior del primer técnico; si no existe, se genera desde la pantalla. */
async function rutaInforme(pagina) {
    await pagina.goto(`${BASE}/informes`)
    await Promise.all([pagina.waitForURL(/\/informes\/\d+/), pagina.locator('tbody button').first().click()])
    return new URL(pagina.url()).pathname
}

// --- Escritorio, claro ---------------------------------------------------
await pantalla('login', '/login', null, null, { tapar: ['code'], completa: true })
await pantalla('panel', '/dashboard', CUENTAS.gerencia, CLAVE_DEMO, { esquemas: ['light', 'dark'] })
await pantalla('bandeja', '/soportes?vista=abiertos', CUENTAS.soporte, CLAVE_DEMO, { esquemas: ['light', 'dark'] })
await pantalla('ficha', rutaFicha, CUENTAS.soporte, CLAVE_DEMO, { esquemas: ['light', 'dark'], completa: true })
await pantalla('clientes', '/clientes', CUENTAS.gerencia, CLAVE_DEMO)
await pantalla('recurrentes', '/clientes/recurrentes', CUENTAS.gerencia, CLAVE_DEMO)
await pantalla('inventario', '/inventario', CUENTAS.gerencia, CLAVE_DEMO)
await pantalla('informe', rutaInforme, CUENTAS.gerencia, CLAVE_DEMO, {
    completa: true,
    // Texto de ejemplo en las cajas, sin guardarlo: el informe no queda modificado.
    preparar: async (pagina) => {
        await pagina.fill('#observaciones', 'Buen mes: resolvió casi todo en remoto y sin escalar. Conviene documentar mejor el diagnóstico cuando la falla es de lentitud.')
        await pagina.fill('#compromisos', 'Registrar la potencia medida en cada caso de lentitud.')
        await pagina.evaluate(() => document.activeElement?.blur())
    },
})
await pantalla('usuarios', '/usuarios', ADMIN.correo, ADMIN.clave)
await pantalla('campo', '/campo', CUENTAS.campo, CLAVE_DEMO)

// --- Celular, claro --------------------------------------------------------
await pantalla('campo', '/campo', CUENTAS.campo, CLAVE_DEMO, { viewport: CELULAR })
await pantalla('bandeja', '/soportes?vista=abiertos', CUENTAS.soporte, CLAVE_DEMO, { viewport: CELULAR })

// --- Campo sin señal: se cierra una visita y queda guardada en el teléfono --
for (const viewport of [CELULAR, ESCRITORIO]) {
    const { contexto, pagina } = await sesion(CUENTAS.campo, CLAVE_DEMO, { viewport })

    // Como en un teléfono que ya usó la app: el service worker controla /campo
    // y tiene la ruta guardada para abrirla sin señal.
    await pagina.goto(`${BASE}/campo`)
    await pagina.evaluate(() => navigator.serviceWorker.ready)
    await pagina.reload()
    await pagina.waitForLoadState('networkidle')

    await pagina.locator('ol a[href*="/campo/visitas/"]').first().click()
    await pagina.waitForURL(/\/campo\/visitas\/\d+/)
    await pagina.waitForLoadState('networkidle')

    await contexto.setOffline(true)
    await pagina.selectOption('#diagnostico', { index: 1 })
    await pagina.fill('#observaciones', 'Se cambió el conector de la acometida y la potencia quedó en -18 dBm.')
    await pagina.getByRole('button', { name: 'Cerrar la visita' }).click()
    await pagina.getByText('Guardado en el teléfono').waitFor()

    // Tras guardar, la pantalla vuelve sola a la ruta, que abre desde el teléfono.
    await pagina.waitForURL(`${BASE}/campo`)
    await pagina.getByText(/Sin señal · 1 por enviar/).waitFor()
    await pagina.evaluate(() => document.fonts.ready)

    const sufijo = viewport === CELULAR ? 'celular' : 'escritorio'
    await pagina.screenshot({ path: `${SALIDA}campo-sin-senal-${sufijo}-claro.png`, animations: 'disabled' })
    hechas.push(`campo-sin-senal-${sufijo}-claro`)
    console.log('✓', `campo-sin-senal-${sufijo}-claro`)

    // El cierre queda solo en este navegador de prueba: se descarta al cerrarlo.
    await contexto.close()
}

await navegador.close()
console.log(`\n${hechas.length} capturas en docs/capturas/`)
