import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Dirección visual "Consola": grafito y azul fibra.
 *
 * Los colores son semánticos y salen de variables CSS (resources/css/app.css),
 * que cambian con la clase `dark` en <html>. Así una sola clase, `bg-superficie`
 * o `text-tinta`, sirve en claro y en oscuro sin escribir `dark:` en cada vista.
 */
const token = (nombre) => `rgb(var(--c-${nombre}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                fondo: token('fondo'),
                superficie: token('superficie'),
                elevada: token('elevada'),
                linea: { DEFAULT: token('linea'), suave: token('linea-suave') },
                tinta: { DEFAULT: token('tinta'), 2: token('tinta-2'), 3: token('tinta-3') },
                menu: { DEFAULT: token('menu'), activo: token('menu-activo'), tinta: token('menu-tinta'), borde: token('menu-borde') },
                acento: {
                    DEFAULT: token('acento'),
                    hover: token('acento-hover'),
                    texto: token('acento-texto'),
                    tinte: token('acento-tinte'),
                    'tinte-texto': token('acento-tinte-texto'),
                },
                peligro: { DEFAULT: token('peligro'), borde: token('peligro-borde') },
                sla: {
                    ok: token('sla-ok'),
                    'ok-fondo': token('sla-ok-fondo'),
                    'ok-barra': token('sla-ok-barra'),
                    atencion: token('sla-atencion'),
                    'atencion-fondo': token('sla-atencion-fondo'),
                    'atencion-barra': token('sla-atencion-barra'),
                    riesgo: token('sla-riesgo'),
                    'riesgo-fondo': token('sla-riesgo-fondo'),
                    'riesgo-barra': token('sla-riesgo-barra'),
                    vencido: token('sla-vencido'),
                    'vencido-oscuro': token('sla-vencido-oscuro'),
                    'vencido-texto': token('sla-vencido-texto'),
                    pausa: token('sla-pausa'),
                    'pausa-borde': token('sla-pausa-borde'),
                },
                estado: {
                    proceso: token('estado-proceso'),
                    seguimiento: token('estado-seguimiento'),
                    sincontacto: token('estado-sincontacto'),
                    enviado: token('estado-enviado'),
                    escalado: token('estado-escalado'),
                },
                recurrente: { DEFAULT: token('recurrente'), fondo: token('recurrente-fondo') },
            },
            borderRadius: {
                control: '6px',
                tarjeta: '8px',
            },
        },
    },

    plugins: [forms],
};
