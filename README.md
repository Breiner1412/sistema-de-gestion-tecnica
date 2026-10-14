# Sistema de Gestión Técnica

Sistema de control operativo para la mesa de soporte de un proveedor de internet y TV
por fibra. Laravel 13 · Livewire 3 (Volt) · MySQL · Tailwind.

![Bandeja de casos: cada caso con su semáforo de SLA, el que vence primero arriba](docs/capturas/bandeja-escritorio-claro.png)

La bandeja ordena los casos abiertos por lo que vence primero. El semáforo de SLA no depende
solo del color: cada estado tiene su forma, su palabra, su barra y su tiempo, y lo único que
va relleno es lo vencido.

### Claro y oscuro

Cada persona elige en el menú entre claro, oscuro o lo que diga su equipo, y queda guardado
en su cuenta. La pantalla de campo va aparte: clara por defecto, con un botón de modo noche.

| Claro | Oscuro |
|---|---|
| ![Bandeja en modo claro](docs/capturas/bandeja-escritorio-claro.png) | ![Bandeja en modo oscuro](docs/capturas/bandeja-escritorio-oscuro.png) |
| ![Ficha del caso en modo claro](docs/capturas/ficha-escritorio-claro.png) | ![Ficha del caso en modo oscuro](docs/capturas/ficha-escritorio-oscuro.png) |
| ![Panel en modo claro](docs/capturas/panel-escritorio-claro.png) | ![Panel en modo oscuro](docs/capturas/panel-escritorio-oscuro.png) |

### Más pantallas

| | | |
|---|---|---|
| ![Mi ruta en el celular](docs/capturas/campo-celular-claro.png)<br>**Mi ruta** · el técnico en el celular | ![Sin señal, un cierre por enviar](docs/capturas/campo-sin-senal-celular-claro.png)<br>**Sin señal** · el cierre queda en el teléfono | ![Bandeja en el celular](docs/capturas/bandeja-celular-claro.png)<br>**Bandeja** en el celular |
| ![Abonados recurrentes](docs/capturas/recurrentes-escritorio-claro.png)<br>**Abonados recurrentes** | ![Informe mensual](docs/capturas/informe-escritorio-claro.png)<br>**Informe mensual** por técnico | ![Inventario](docs/capturas/inventario-escritorio-claro.png)<br>**Inventario** y kardex |
| ![Clientes](docs/capturas/clientes-escritorio-claro.png)<br>**Clientes** | ![Usuarios](docs/capturas/usuarios-escritorio-claro.png)<br>**Usuarios** y roles | ![Entrar](docs/capturas/login-escritorio-claro.png)<br>**Entrar** con las cuentas demo |

Todos los datos de las capturas son ficticios. Se regeneran con `npm run capturas`
(ver [`scripts/capturas.mjs`](scripts/capturas.mjs)).

> **¿Cómo se usa?** El [manual de usuario](docs/manual-usuario.md) lo explica por rol, paso
> a paso y sin términos técnicos: entrar, crear un caso, leer el semáforo, escalar, cerrar
> una visita sin señal, el informe del mes y más, con preguntas frecuentes al final.

Este archivo, en cambio, explica **por qué está hecho así**.

---

## Por qué existe

La operación en la que se basa este proyecto ya tiene un ERP —SAE Plus— para facturación,
contratos, cortes y reconexiones. Y aun así el equipo de soporte lleva **un Excel al lado**,
con más de 5.000 casos en un semestre.

Ese Excel es la evidencia del hueco: el ERP administra el negocio, pero nadie controla el
turno. En ese archivo no se puede saber si un caso lleva dos horas o dos días sin tocar; el
12% de los casos queda en "sin contacto" y nadie los vuelve a llamar; y el diagnóstico
técnico —el dato que explica *por qué* se cae el servicio— solo sirve si alguien se sienta a
contar filas a mano.

> **Este sistema no reemplaza el ERP del ISP. Reemplaza el Excel que llevan al lado.**

De ahí sale la frontera del alcance:

| Adentro | Afuera |
|---|---|
| Ciclo de vida del caso | Facturación y recaudo |
| Tiempos y cumplimiento de SLA | Cortes y reconexiones |
| Responsables y escalamiento | PQRS formales |
| Diagnóstico y analítica de fallas | El plan del cliente como fuente de verdad |
| Visitas, evidencia e inventario consumido | Integración con Mikrotik / OLT |

Un cambio de plan aquí es una **solicitud con seguimiento** —quién la pidió, quién la
ejecutó, cuánto se demoró—, no la ejecución del cambio.

---

## El flujo real

Un cliente se queja por uno de tres canales: **caja** (presencial), **call center** o el
**WhatsApp de soporte**. Si el primer nivel lo resuelve ahí mismo, no se registra nada. Solo
sube al sistema lo que ese primer contacto no pudo cerrar.

```mermaid
flowchart TD
    A[Caja · Call center · WhatsApp] -->|no se resolvió| B[Nivel 2 · Técnico de soporte]
    A -->|resuelto en el momento| Z[No se registra]
    B --> C{¿Puede resolverlo?}
    C -->|sí| D[Solucionado con diagnóstico]
    C -->|cliente no contesta| E[Sin contacto · reintento]
    C -->|lo supera| F{¿Qué necesita?}
    C -->|no aplica| G[Cancelado]
    E -->|3 intentos fallidos| H[Cerrado sin contacto]
    E -->|contesta| B
    F -->|problema de red| I[Nivel 3 · Ingeniería de redes]
    F -->|requiere presencia| J[Visita en terreno]
    I -->|responde| B
    J -->|visita ejecutada| D
    J -->|no se pudo hacer| B
```

Los 5.058 casos del histórico son, entonces, **los fracasos de resolución de primer nivel**,
no el volumen total de quejas.

---

## Roles

Los nombres siguen el flujo real, no los del Excel original —donde la columna "INGENIERO"
en realidad contenía a los técnicos de soporte.

| Rol | Qué hace |
|---|---|
| `call_center` | Primer nivel: caja, teléfono y WhatsApp. Registra los casos que no pudo cerrar. |
| `tecnico_soporte` | Nivel 2, remoto. Es el dueño del reloj de SLA. |
| `ingeniero_redes` | Nivel 3. Recibe los casos escalados y los devuelve con respuesta. |
| `tecnico_campo` | Ejecuta las visitas. Solo ve las suyas. |
| `gerente` | Consulta el panel y toda la operación. |
| `admin` | Todo, incluida la administración de usuarios. |

---

## Reglas de negocio

Estas reglas viven en el **modelo**, no en los formularios. Cualquier pantalla, comando o
job que mueva un caso pasa por ellas.

### Máquina de estados

`App\Models\Soporte::TRANSICIONES` define qué movimientos son válidos desde cada estado.
Un salto inválido lanza `TransicionInvalidaException`; la interfaz dibuja sus botones a
partir del mismo mapa, así que nunca ofrece algo imposible.

Estados: `pendiente`, `en_proceso`, `seguimiento`, `sin_contacto`, `enviado_tecnico`,
`escalado_n3`, `solucionado`, `cancelado`, `cerrado_sin_contacto`.

Ningún caso se cierra sin diagnóstico, y ninguno se cierra mientras tenga una visita abierta.

### Criticidad derivada

La criticidad **no la escoge el asesor**. El criterio de la operación es: si el servicio
está caído se atiende de inmediato, el resto va a cinco días hábiles. Eso se calcula:

- `tipo_solicitud = sin_internet` → **inmediata**
- falla marcada como `critica` en `tipos_falla` (sin señal de TV, fallas con la TV) → **inmediata**
- cambio de plan o de titular → **normal**
- resto → **normal**

Se puede elevar a mano, pero exige motivo y queda registrado en el historial.

### Los tres relojes

El reloj del nivel 2 corre **solo en horario hábil** y **se detiene** cuando el caso deja de
depender de él:

- visita programada → pausa
- escalado a redes → pausa
- esperando que el cliente conteste → pausa

Al volver, se reanuda con los minutos que quedaban. Las otras dos ventanas —cumplimiento de
la visita y tiempo de respuesta de redes— se miden aparte.

`App\Support\CalendarioHabil` cuenta ese tiempo respetando la jornada, los fines de semana y
los **18 festivos de Colombia**, que se calculan y no se listan: seis fijos, siete corridos
al lunes por la Ley 51 de 1983 (Emiliani) y cinco dependientes de la Pascua, con el algoritmo
de Meeus/Jones/Butcher.

### Sin contacto con política

Cada intento se cuenta y programa el siguiente a cuatro horas hábiles. Al tercero, el caso se
cierra como `cerrado_sin_contacto` con constancia —en vez de quedar flotando, que es lo que
pasaba en el Excel con 364 casos del semestre.

Quien cierra el círculo es el comando `soportes:reintentar-contacto`, que corre cada cuarto de
hora: devuelve a la cola del nivel 2 los casos cuyo reintento ya venció —reanudando el reloj— y
cierra los que agotaron los intentos. Sin él la hora de reintento pasaría y nadie volvería a
llamar, que es justamente el problema que se quería resolver. Esas transiciones quedan marcadas
en el historial como automáticas, sin usuario.

### El técnico en terreno

La visita es el único punto del flujo donde el sistema no está: el técnico está
en un sótano, en una zona rural o en un edificio con paredes gruesas, y el cliente
está delante esperando para firmar. Si cerrar la visita exige señal, el técnico lo
apunta en un papel y lo pasa por la noche —o no lo pasa—, que es el mismo agujero
que este proyecto existe para tapar.

Por eso `/campo` es una pantalla aparte de `/ordenes`, con su propio layout: se usa
de pie, con una mano y a veces con guantes. La ruta del día, un toque para llamar,
otro para abrir el mapa, y el cierre con diagnóstico, trabajo realizado, material
consumido, hasta seis fotos y la firma del cliente en el lienzo.

**El cierre nunca depende de la red.** Se guarda en el teléfono (IndexedDB) y se
envía solo cuando vuelve la señal. Tres decisiones sostienen eso:

- **El identificador del cierre lo pone el teléfono**, antes de enviar. Un reenvío
  después de una sincronización a medias trae el mismo `uuid`, el servidor lo
  reconoce y responde que sí sin cerrar el caso dos veces ni descontar el material
  dos veces. Sin esto, la cola sería peor que el papel.
- **La hora que manda es la del terreno**, no la de la sincronización: si cerró a
  las 10:05 sin señal y sincronizó a las 14:30, la visita terminó a las 10:05. Se
  descarta si es imposible —un reloj de celular mal puesto no fecha una visita el
  mes que viene—.
- **Un fallo que se arregla reintentando no es lo mismo que uno que no.** Un 5xx o
  una red caída se reintentan; un 422 se descarta y se avisa, porque reintentar a
  ciegas algo que el servidor rechazó por datos inválidos deja la cola atascada
  para siempre. Un 419 se trata aparte: los datos están bien, lo que caducó es la
  sesión.

Cerrar una visita mueve el estado de la orden, cierra o reabre el caso por la
máquina de estados y descuenta inventario. Eso vive en un solo sitio —el servicio
`CierreDeVisita`— que usan por igual la pantalla de escritorio y el celular; dos
copias de esa lógica habrían divergido en semanas.

Un detalle que solo aparece con la cola: un cierre que llega tres horas tarde puede
encontrarse sin stock del material que el técnico ya usó. La visita **no se rechaza**
—ya está hecha—; se cierra igual y el faltante queda avisado, que es lo único
honesto que se puede hacer con algo que ya ocurrió.

La pantalla es instalable: el manifiesto y el service worker la dejan abrir en el
celular como una app, sin barra del navegador, y con la última versión de la ruta
guardada para cuando no haya señal. El service worker **solo** sirve para que la
pantalla abra; el envío no pasa por él.

### Alertas de SLA

El semáforo de la bandeja solo lo ve quien está mirando la pantalla, y un caso inmediato tiene
cuatro horas hábiles: si nadie abre la bandeja en esas cuatro horas, el color no sirvió de nada.
`soportes:alertar-sla` corre cada hora en horario hábil y avisa por correo:

- al **técnico asignado**, cuando su caso cruza el 85 % del tiempo —el mismo umbral del
  semáforo, para que la pantalla y el correo no digan cosas distintas—;
- a **gestión** (`admin` y `gerente`, configurable), cuando un caso ya se venció o cuando
  lleva rato en riesgo y **nadie lo ha tomado**, que si no no aparecería en la bandeja de nadie.

Dos decisiones que evitan que el aviso se vuelva ruido y deje de leerse:

- **un solo correo por persona**, con lo vencido primero y lo que está por vencerse después;
  un correo por caso son quince correos al mismo técnico en un día movido;
- **dos avisos por caso como máximo** —uno al entrar en riesgo, otro al vencerse—. El nivel ya
  notificado queda guardado en el caso (`alerta_sla_nivel`), así que correr el comando cada
  hora no repite nada.

Fuera de horario no se avisa: el reloj está parado, así que un correo de madrugada no diría
nada que no se pueda decir a las siete. Se apaga con `sla.alertas.activas` en `config/sla.php`.

### Abonados recurrentes

La operación los llamaba reincidentes y los sacaba aparte, porque son **dos problemas
distintos disfrazados de uno**: o hay una falla real que nadie resolvió de raíz, o al cliente
nunca se le logra contactar y el caso se reabre sin avanzar. El sistema los separa mirando
cuántos de sus casos murieron sin contacto.

Umbrales en `config/sla.php`: 2 reportes en 60 días entran a la lista, 3 o más en un año la
marcan como situación a revisar. Sobre el histórico real esa regla deja **537 abonados (13%)**
con dos reportes en dos meses, de los cuales 471 son falla recurrente y 66 falta de contacto.

La alerta en la ficha del caso se calcula contra **la fecha de ese caso**, no contra hoy: así
sigue siendo cierta cuando se revisa un caso viejo y funciona igual sobre el histórico
importado.

### Exportación

La bandeja y la lista de recurrentes se descargan en CSV **con los filtros puestos**: lo que
se exporta es exactamente lo que se está viendo. La descarga se transmite fila por fila, así
que exportar los 5.000 casos no carga nada en memoria.

El archivo sale con separador de punto y coma y BOM UTF-8, que es lo que necesita Excel en
configuración regional española para no meter la fila entera en una celda ni romper las
tildes.

### Escalamiento con dueño

Escalar a nivel 3 no es una bandera: se elige el ingeniero de redes, se exige motivo, y solo
él (o un admin) puede devolver el caso al nivel 2 con su respuesta.

### Informe mensual de rendimiento

La coordinación hacía una vez al mes un informe por técnico —cifras del período y un bloque de
observaciones firmado— para que cada quien llevara registro de su mes y supiera en qué
mejorar. El sistema lo reproduce: casos por día, fallas, estados, escalamientos, tiempo medio
de resolución, y la comparación con el promedio del equipo, porque una cifra suelta no dice
nada sin saber qué hicieron los demás.

Al publicarlo, **las métricas se congelan** en la tabla: un documento firmado no puede cambiar
de contenido porque un caso viejo se movió después. El técnico lee el suyo y su apertura queda
registrada como acuse de recibo. La vista está pensada para imprimirse, con sus dos líneas de
firma.

---

## Modelo de datos

```
clientes ──< contratos ──< equipos_instalados
    │            │
    └─────< soportes >──── users (registra / nivel 2 / escalado a)
              │  │
              │  ├── tipos_falla        (qué reporta el cliente)
              │  ├── diagnosticos       (qué encontró el técnico)
              │  ├── soporte_cambio_plan     · planes
              │  ├── soporte_cambio_titular
              │  └── soporte_historial_estados
              │
              └──< ordenes_trabajo ──< materiales_orden >── inventario
                        │                                       │
                        └──< evidencias                movimientos_inventario
```

Notas de diseño:

- **`codigo_abonado`** es la llave con la que la operación identifica a un cliente día a día,
  por encima de la cédula. Es también el campo por el que este sistema podría sincronizarse
  algún día con el ERP.
- **`numero_soporte`** (`SGT-2026-000001`) se genera solo. El Excel no tenía identificador de
  caso, así que no había forma de referirse a uno por teléfono.
- **`tiempo_respuesta`** es ingreso → asignación; **`tiempo_resolucion`** es ingreso → cierre.
  Ambos en minutos hábiles. Son métricas distintas y el Excel no permitía calcular ninguna.
- Todo usa **soft deletes**: los casos históricos siguen apuntando a quien los atendió.

---

## Estructura del código

```
app/
  Models/
    Soporte.php                 máquina de estados, criticidad, acciones del flujo
    Concerns/GestionaSla.php    reloj de SLA: iniciar, pausar, reanudar, semáforo
    OrdenTrabajo.php            visitas: programación y cumplimiento
    User.php                    roles y scopes por nivel
  Console/Commands/
    ReintentarContacto.php      reintentos de los casos sin contacto
    ImportarHistorico.php       carga del histórico seudonimizado
  Support/
    CalendarioHabil.php         horas y días hábiles, festivos de Colombia
    MetricasTecnico.php         cifras mensuales por técnico y comparación con el equipo
    ExportadorCsv.php           descarga en CSV transmitida fila por fila
lang/es/                        mensajes de validación y de sesión en español
  Observers/
    MaterialOrdenObserver.php   descuento atómico de inventario
  Exceptions/
    TransicionInvalidaException.php
config/
  sla.php                       jornada, tiempos por criticidad, umbrales, reintentos
resources/views/livewire/
  dashboard/    panel de indicadores
  soportes/     bandeja, alta y ficha del caso
  ordenes/      visitas programadas
  clientes/     maestro de clientes, contratos, equipos y recurrentes
  informes/     informe de rendimiento mensual por técnico
  usuarios/     administración del equipo
  inventario/   bodega y kardex
database/
  migrations/   esquema
  seeders/      CatalogoSeeder · UsuarioSeeder · DemoSeeder
  data/         histórico seudonimizado en CSV
```

---

## Instalación

Requiere **PHP 8.3+** (Laravel 13 no arranca con 8.2), MySQL y Node.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Crea la base y ajusta el `.env`:

```sql
CREATE DATABASE gestion_tecnica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Antes de sembrar, define en el `.env` el administrador (`ADMIN_EMAIL`, `ADMIN_PASSWORD`) y la
clave de las cuentas demo (`DEMO_PASSWORD`): ninguna clave vive en el código.

```bash
php artisan migrate --seed
npm run dev
```

Las fotos y firmas de las visitas se guardan en el disco privado (`storage/app/private`) y se
sirven por una ruta que pregunta a la política de la visita: no hace falta `storage:link`.

La pantalla de campo (`/campo`) usa la cámara y el GPS, y los navegadores solo los
dan sobre HTTPS o en `localhost`. Para probarla desde el celular en la misma red hace
falta un túnel (`php artisan serve` + ngrok, o el HTTPS de Laragon); por `http://` a
una IP local el navegador bloquea las dos cosas.

### Tareas programadas

Dependen del planificador: el reintento de los casos sin contacto (cada cuarto de hora), las
alertas de SLA (cada hora, de lunes a viernes entre las 7 y las 18, hora de Colombia), la
vigilancia de la cola (cada 5 minutos) y, en modo demo, el reinicio nocturno. En Docker los
corre el contenedor `soporte-tareas` y los correos los envía `soporte-cola` (ver
[Despliegue](#despliegue-con-docker-y-caddy)). Sin Docker basta una entrada de cron, más un
`php artisan queue:work` vivo (con supervisor o systemd):

```
* * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

En local, `php artisan schedule:work` deja el planificador corriendo. Ambos comandos aceptan
`--dry-run` para ver a quién tocarían sin enviar ni cambiar nada:

```bash
php artisan soportes:reintentar-contacto --dry-run
php artisan soportes:alertar-sla --dry-run
```

Las alertas salen por el `MAIL_MAILER` configurado. En local, `log` las deja en
`storage/logs/laravel.log`, que es suficiente para verlas sin montar un servidor de correo.

En Laragon el sitio queda en `http://sistema-gestion-tecnica.test`; si el vhost no resuelve,
`php artisan serve` sirve en `http://127.0.0.1:8000`.

### Medir una pantalla lenta

`PERFIL_CONSULTAS=true` en el `.env` deja una línea por petición en el registro:

```
perfil {"ruta":"GET /clientes/9","consultas":6,"ms_base":38.8,"ms_total":7067.7}
```

Tres cifras que separan los casos que se confunden entre sí:

- muchas consultas y poco tiempo en cada una → un N+1;
- pocas consultas y una lenta → falta un índice o sobra un JOIN;
- **poco tiempo en la base y mucho en total → no es la base.**

La línea de arriba es real y es el tercer caso: seis consultas, 39 ms contra la base, siete
segundos en total. La ficha del abonado no estaba lenta, estaba **rota** —`EquipoInstalado`
apuntaba a una tabla que no existía, porque Eloquent pluraliza solo la última palabra del
nombre de la clase— y los siete segundos eran Laravel dibujando su página de error. Sin medir,
lo natural habría sido buscar un N+1 que no estaba.

Se apaga cuando se termina de medir: escuchar todas las consultas cuesta, y en las pruebas ni
se engancha.

### Cuentas de prueba

El administrador no viene sembrado con datos fijos: se crea con `ADMIN_EMAIL` y
`ADMIN_PASSWORD` del `.env` (sin ellos no hay admin).

Todas con la contraseña de `DEMO_PASSWORD` del `.env`; si no se define, quedan con una
clave aleatoria y no se puede entrar con ellas.

| Correo | Rol |
|---|---|
| `gerencia@sgt.local` | Gerente |
| `redes1@sgt.local` | Ingeniero de redes (N3) |
| `campo1@sgt.local` | Técnico de campo |

Los técnicos de soporte y los asesores de primer nivel usan el patrón
`nombre.apellido@sgt.local`; se listan en `database/seeders/UsuarioSeeder.php`.

Con `DEMO_MODE=true` el login publica una cuenta por rol (ninguna de administrador) con su
clave, y esas cuentas no se pueden borrar ni cambiarles el correo, el nombre o la contraseña.
La lista está en `config/demo.php`.

#### Reinicio nocturno

En modo demo, `demo:reiniciar` corre todas las noches a las 03:00 (hora de Colombia) desde el
programador de tareas: borra las fotos y firmas subidas, hace `migrate:fresh --seed`, vuelve a
importar el histórico y siembra sus contratos. El login lo avisa. Fuera del modo demo el comando
se niega a correr, porque borra la base entera.

```bash
php artisan demo:reiniciar                  # completo, con el histórico
php artisan demo:reiniciar --sin-historico  # solo la siembra base, en segundos
```

Las sesiones, la cola y la caché viven en la base, así que a esa hora todos los visitantes
salen de su sesión.

### Configuración del SLA

Todo lo ajustable vive en `config/sla.php`: jornada laboral, tiempos por criticidad,
umbrales del semáforo, política de reintentos y festivos propios de la empresa.

---

## Despliegue con Docker y Caddy

Pensado para una VM Linux con Docker donde un **Caddy compartido** con otras apps pone el
HTTPS. La pila es una sola imagen (PHP 8.3-FPM + Nginx) en tres papeles, más MySQL:

| Contenedor | Qué hace | Redes |
|---|---|---|
| `soporte-app` | Nginx en el puerto 80 y PHP-FPM. Al arrancar migra y, si la base está vacía, la siembra (`sistema:preparar`). | `interna` y `proxy` |
| `soporte-cola` | `queue:work`: envía los correos de las alertas de SLA. | `interna` |
| `soporte-tareas` | `schedule:work`: alertas, reintentos, vigilancia de la cola y reinicio de la demo. | `interna` |
| `soporte-mysql` | MySQL 8.4, en hora de Colombia. | solo `interna` |

Ningún contenedor publica puertos: Caddy llega a `soporte-app` por la red externa `proxy`, y
MySQL solo es visible dentro de `interna`. Las fotos y firmas viven en el volumen `archivos`,
compartido por los tres contenedores de la aplicación.

### Caddy con IP fija

Laravel solo cree en las cabeceras `X-Forwarded-*` (https, IP real del visitante) si vienen
de la IP de Caddy, así que Caddy necesita una IP fija en la red `proxy`. Por defecto es
`172.30.0.2` (`TRUSTED_PROXIES`). Si la red `proxy` de la VM aún no existe:

```bash
docker network create --subnet 172.30.0.0/24 --ip-range 172.30.0.128/25 proxy
```

`--ip-range` deja las IPs automáticas en la mitad alta, para que la `.2` nunca se la lleve
otro contenedor. En el `docker-compose.yml` del Caddy compartido:

```yaml
services:
  caddy:
    networks:
      proxy:
        ipv4_address: 172.30.0.2
networks:
  proxy:
    external: true
```

Si la red `proxy` ya existe con otra subred (`docker network inspect proxy`), elige una IP
libre dentro de ella para Caddy y ponla en `TRUSTED_PROXIES`.

El bloque del sitio, con las cabeceras de seguridad, está en
[`docker/Caddyfile.ejemplo`](docker/Caddyfile.ejemplo):

```caddy
soporte.tudominio.com {
	encode zstd gzip
	import cabeceras-soporte   # HSTS, nosniff, X-Frame-Options, CSP... (ver el archivo)

	request_body {
		max_size 32MB          # un cierre de visita con fotos pesa hasta ~26 MB
	}

	reverse_proxy soporte-app:80
}
```

### Primer despliegue

```bash
git clone <repositorio> soporte && cd soporte
cp .env.production.example .env        # y llenar lo marcado con <...>
docker compose run --rm --no-deps soporte-app php artisan key:generate --show   # -> APP_KEY
docker compose up -d --build
docker compose ps                      # soporte-app debe quedar "healthy"
```

En el primer arranque, con `DEMO_MODE=true` se carga la demo completa (casos, histórico y
contratos); con `DEMO_MODE=false`, solo el administrador del `.env` y los catálogos. Después,
reiniciar los contenedores nunca vuelve a sembrar.

Para actualizar:

```bash
git pull
docker compose up -d --build           # migra solo al arrancar soporte-app
docker compose logs -f soporte-app soporte-cola soporte-tareas
```

### Respaldo con mysqldump

La base:

```bash
mkdir -p respaldos
docker exec soporte-mysql sh -c 'exec mysqldump --single-transaction --routines --triggers --no-tablespaces -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip > respaldos/gestion_tecnica-$(date +%F).sql.gz
```

Las fotos y firmas (el volumen `archivos`):

```bash
docker run --rm -v soporte_archivos:/datos:ro -v "$PWD/respaldos":/respaldo alpine \
  tar czf /respaldo/archivos-$(date +%F).tar.gz -C /datos .
```

Todos los días a las 02:30, con cron en la VM (en la demo se reinicia todo a las 03:00, así que
solo tiene sentido en una operación real):

```
30 2 * * * cd /ruta/soporte && docker exec soporte-mysql sh -c 'exec mysqldump --single-transaction --routines --triggers --no-tablespaces -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' | gzip > respaldos/gestion_tecnica-$(date +\%F).sql.gz && find respaldos -name '*.sql.gz' -mtime +14 -delete
```

Restaurar:

```bash
gunzip -c respaldos/gestion_tecnica-AAAA-MM-DD.sql.gz \
  | docker exec -i soporte-mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

### Probarlo en el PC

`docker-compose.local.yml` va encima del de producción: agrega un Caddy propio en
`http://localhost:8080` y crea la red `proxy` con la misma IP fija. Las variables van en
`.env.local` (fuera de git). En PowerShell:

```powershell
docker compose -f docker-compose.yml -f docker-compose.local.yml --env-file .env.local up -d --build
docker compose -f docker-compose.yml -f docker-compose.local.yml --env-file .env.local ps
start http://localhost:8080
```

Si el 8080 está ocupado, `PUERTO_LOCAL=8081` en `.env.local`. Para apagar (con `-v` borra
también la base y las fotos):

```powershell
docker compose -f docker-compose.yml -f docker-compose.local.yml --env-file .env.local down
```

---

## Sobre los datos

El histórico que originó este diseño se usó **con autorización**, y de él se conservó
únicamente lo que es conocimiento del dominio: los catálogos (13 diagnósticos, 13 tipos de
falla, 30 planes), las proporciones reales de cada tipo de caso y la forma del equipo.

**Todos los nombres, cédulas y teléfonos son sintéticos**, tanto de clientes como de personal.
Un panel que muestra cuántos casos resolvió cada quien y en cuánto tiempo es información de
desempeño de personas reales, y este es un proyecto público.

### El histórico importable

`database/data/` trae el semestre completo de la operación, ya seudonimizado:
**4.999 casos y 4.114 abonados** entre diciembre de 2025 y julio de 2026.

```bash
php artisan soportes:importar-historico
```

El importador **no pisa** los clientes que ya existen, para no deshacer ediciones hechas a
mano. Con `--actualizar` sí refresca nombre y teléfono, que es como se llevan a la base los
nombres reasignados. Y con `--fresh` borra los casos antes de importar: sin eso, correrlo dos
veces mete el histórico dos veces y **duplica todas las cifras** —los recurrentes, el informe
mensual, el panel—.

#### Los nombres falsos

`datos:seudonimizar-nombres` genera los nombres de los abonados. Está en el repositorio a
propósito: es la parte del proceso de anonimización que puede publicarse, porque no necesita el
Excel original. Es determinista —el nombre sale del código de abonado—, así que el CSV no
cambia entre commits sin motivo.

```bash
php artisan datos:seudonimizar-nombres --dry-run   # ver una muestra
php artisan datos:seudonimizar-nombres
php artisan soportes:importar-historico --actualizar
```

Existe por un error que vale la pena dejar escrito. El primer generador combinaba una lista
corta de nombres con una lista corta de apellidos: **144 combinaciones para 4.114 personas**,
cada nombre repetido veintinueve veces. Y como la lista de clientes se ordena por nombre, las
copias caían juntas y la primera pantalla parecía un solo cliente clonado. La prueba que lo
acompaña no comprueba que el generador produzca un nombre: comprueba que produzca 4.200
distintos, que es lo que no se verificó la primera vez.

#### Los contratos

El Excel era una hoja de incidencias: traía el caso y la falla, pero nada del contrato ni del
equipo instalado, porque eso vivía en el ERP del ISP. Los abonados importados quedan entonces
sin contrato, y la ficha del cliente muestra una sección vacía que parece un fallo del sistema
y es un hueco del origen.

```bash
php artisan db:seed --class=ContratoHistoricoSeeder
```

Eso lo rellena con **datos inventados** —fechas, planes y seriales plausibles, nada más—. Va en
un seeder aparte y no dentro del importador justamente para que la línea quede clara: el
importador trae lo que hubo, el seeder rellena lo que nunca hubo.

Qué se conservó del original: la fecha y hora de cada caso, su tipo, el servicio afectado, la
falla reportada, el diagnóstico, el estado final, la fecha de cierre y la concentración real de
la carga entre el equipo.

Qué se transformó o se descartó, y por qué:

| Dato | Qué se hizo |
|---|---|
| Nombre, cédula y teléfono del abonado | Reemplazados por sintéticos, con un mapa estable: el mismo abonado real siempre da el mismo cliente ficticio, así el historial por cliente sigue siendo coherente |
| Código de abonado | Renumerado conservando el prefijo, porque el código original identifica una cuenta real |
| Nombres del personal | Reducidos a un índice de carga que el importador asigna a los usuarios sembrados |
| Observaciones y texto libre | **Descartados por completo**: a veces traían nombres, direcciones y teléfonos, y no aportan nada al análisis |
| Canal de ingreso | **Simulado**: el Excel nunca registró por dónde entraba la queja |
| Tiempo de respuesta | **Vacío**: el Excel nunca registró a qué hora se tomaba un caso. Solo hay tiempo de resolución |

Las dos últimas filas importan: el panel muestra esos campos, y conviene saber cuáles son
medición y cuáles son relleno.

---

## Estado

| Fase | Alcance | Estado |
|---|---|---|
| 0 | Modelo del flujo real: roles, canal, criticidad, calendario hábil, SLA con pausa, máquina de estados, visitas programadas | Hecha |
| 1 | Maestros: clientes y contratos, usuarios y técnicos, inventario con kardex | Hecha |
| 2 | Bandeja del nivel 2 con semáforo y vistas rápidas | Hecha |
| 3 | Reintentos automáticos de "sin contacto" por comando programado, con pruebas | Hecha |
| 4 | Pantalla de campo: ruta del día, cierre con foto, firma y GPS, y cola offline | Hecha |
| 5 | Importador del histórico seudonimizado (4.999 casos, 4.114 abonados) | Hecha |
| 6 | Recurrentes e informe mensual de rendimiento por técnico | Hecha |
| 7 | Interfaz en español y exportación de datos a CSV | Hecha |
| 8 | Alertas por correo al entrar en riesgo o vencerse un SLA | Hecha |
| 9 | Auditoría previa al despliegue: políticas, modo demo, Docker y Caddy | Hecha |

## Pruebas

```bash
php artisan test     # PHP, sobre SQLite en memoria
npm run test:js      # la decisión de la cola offline del celular
```

En GitHub Actions ([`.github/workflows/pruebas.yml`](.github/workflows/pruebas.yml)) la suite
corre además contra **MySQL 8.4**, que es lo que hay en producción: SQLite no tiene `YEAR()`,
no altera claves foráneas ni `CHECK` y es laxo con los tipos. También prueba las migraciones de
ida, vuelta completa e ida, el build de Vite y que la imagen de Docker se construya. En local,
con un MySQL a mano:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=pruebas DB_USERNAME=root DB_PASSWORD=... php artisan test
```

Cubren lo que más duele si se rompe:

- `tests/Unit/CalendarioHabilTest.php` — los 18 festivos de cada año, la Ley Emiliani, y que
  la aritmética salte fines de semana y festivos.
- `tests/Feature/SoporteFlujoTest.php` — numeración, criticidad derivada, transiciones válidas
  e inválidas, asignación, pausa y reanudación del SLA, cierre y política de sin contacto.
- `tests/Feature/ReintentarContactoTest.php` — el comando programado, incluido `--dry-run`.
- `tests/Feature/ImportarHistoricoTest.php` — el importador: numeración sin choques, criticidad
  derivada igual que en el modelo, minutos hábiles, filas omitidas y `--limit`.
- `tests/Feature/RecurrenciaTest.php` — la ventana de reincidencia y la distinción entre falla
  recurrente y falta de contacto.
- `tests/Feature/InformeMensualTest.php` — métricas del mes, comparación con el equipo y el
  congelado de cifras al publicar.
- `tests/Feature/ExportacionTest.php` — el CSV (BOM, separador, normalización, generadores)
  y que los mensajes salgan en español.
- `tests/Feature/AlertaSlaTest.php` — a quién se avisa y a quién no: el técnico asignado, la
  copia a gestión, el caso que nadie tomó, el reloj en pausa, y que no se repita el aviso.
- `tests/Feature/CierreVisitaTest.php` — el cierre desde el celular: idempotencia del reenvío,
  la hora del terreno (y la imposible), fotos y firma en disco, permisos, material que ya no
  alcanza y la visita que ya cerró otro.
- `tests/Feature/SeudonimizarNombresTest.php` — que el generador de nombres no repita ninguno
  en una base de 4.200, que sea determinista y que no toque cédula, teléfono ni código.
- `tests/Feature/ModelosTest.php` — que cada modelo apunte a una tabla que exista.

Las migraciones que tocan llaves foráneas se saltan ese paso en SQLite, que no las admite sobre
tablas existentes; en MySQL sí se crean.

### Deuda conocida

- De la cola offline solo está probada la decisión por código HTTP y el dueño de cada
  cierre (`tests/js`); el guardado en IndexedDB, el candado entre pestañas y el service
  worker se probaron a mano, no con un navegador automatizado.
- El service worker cachea el HTML de `/campo`, así que el token CSRF de una página muy
  vieja puede caducar. Se detecta (419), el cierre queda guardado y la pantalla pide volver
  a entrar.
- La CSP lleva `unsafe-eval` porque Livewire trae Alpine, que evalúa sus expresiones. Se
  quitaría con la versión CSP de Alpine.

## Licencia

[GNU AGPL-3.0](LICENSE). En corto y sin tecnicismos:

- Puede leerlo, estudiarlo, ejecutarlo y modificarlo.
- Si lo modifica y lo distribuye, o si **lo usa para dar un servicio por red**, tiene que
  publicar su versión bajo la misma licencia. Esa segunda parte es lo que distingue a la AGPL
  de licencias más permisivas, y es a propósito: este sistema está pensado para correr como
  servicio, que es justo el caso que otras licencias dejan abierto.

El titular de los derechos es Breiner Stiven Guisao Rodríguez. Para usarlo en un producto
cerrado hay **licencia comercial** disponible: escriba a rodriguezbreiner125@gmail.com.

Esto es un resumen para orientarse, no asesoría legal; lo que vale es el texto de la licencia.

## Nota sobre los datos

El histórico viene de una operación real, con permiso expreso para usarlo. **Ningún dato
personal de terceros está en este repositorio**: nombres, cédulas, teléfonos y códigos de
abonado son sintéticos, el texto libre se descartó entero y los nombres del personal también
se reemplazaron. Lo que se conservó son las proporciones, que es lo que hace útil el análisis
sin exponer a nadie. El detalle completo está más arriba, en *El histórico importable*.
