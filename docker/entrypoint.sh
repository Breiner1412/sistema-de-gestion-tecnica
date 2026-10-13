#!/bin/sh
# Arranque de la imagen. La misma imagen sirve para los tres contenedores de la
# aplicación; el primer argumento dice cuál es:
#
#   web     soporte-app: migra, siembra si la base está vacía y levanta
#           Nginx + PHP-FPM.
#   cola    soporte-cola: queue:work (los correos de las alertas de SLA).
#   tareas  soporte-tareas: schedule:work (alertas, reintentos, reinicio demo).
#
# Cualquier otro argumento se ejecuta tal cual (por ejemplo: php artisan tinker).
set -e

cd /var/www/html

como_app() {
    su-exec www-data "$@"
}

# La caché de configuración se arma al arrancar y no en la imagen: depende de
# las variables de entorno del contenedor, que la imagen no conoce.
preparar_cache() {
    como_app php artisan config:cache
    como_app php artisan route:cache
    como_app php artisan view:cache
    como_app php artisan event:cache
}

esperar_base() {
    intentos=0
    until como_app php artisan db:show >/dev/null 2>&1; do
        intentos=$((intentos + 1))
        if [ "$intentos" -ge 60 ]; then
            echo "La base de datos no respondió en 2 minutos." >&2
            exit 1
        fi
        sleep 2
    done
}

case "${1:-web}" in
    web)
        preparar_cache
        esperar_base
        como_app php artisan sistema:preparar
        como_app php artisan storage:link --force >/dev/null 2>&1 || true
        exec supervisord -c /etc/supervisord.conf
        ;;
    cola)
        preparar_cache
        esperar_base
        # --max-time: el proceso se recicla cada hora y no acumula memoria.
        exec su-exec www-data php artisan queue:work --tries=3 --backoff=60 --max-time=3600 --sleep=3
        ;;
    tareas)
        preparar_cache
        esperar_base
        exec su-exec www-data php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
