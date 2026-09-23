# Manual de usuario

Sistema de Gestión Técnica — cómo se usa, según lo que a cada quien le toca hacer.

No hace falta leerlo entero. Busca tu rol y lee esa parte.

---

## Antes de empezar

### Entrar

El sistema se abre en el navegador con la dirección que le dé su empresa. Se entra con el
correo y la contraseña que le entregaron.

Si se le olvidó la contraseña, hay un enlace debajo del formulario. Si el correo no le llega
o su cuenta está bloqueada, eso lo resuelve quien administra el sistema; usted no puede
desbloquearse solo.

### Qué ve cada quien

El menú de arriba cambia según su rol. No es que le falten opciones: es que cada rol ve lo
suyo.

| Rol | Para qué entra |
|---|---|
| **Call center** | Registra los casos que llegan por teléfono, caja o WhatsApp |
| **Técnico de soporte** | Atiende los casos en remoto: es el nivel 2 |
| **Ingeniero de redes** | Recibe lo que el nivel 2 no pudo resolver |
| **Técnico de campo** | Hace las visitas y las cierra desde el celular |
| **Gerente / Administrador** | Ve todo, escribe los informes del mes y administra el equipo |

---

## Si usted registra los casos (call center)

### Registrar un caso nuevo

**Soportes → + Nuevo caso.**

Busque primero al abonado por nombre, cédula o código. Si no aparece, créelo con **Clientes →
+ Nuevo cliente** y vuelva.

Cuatro cosas que le van a pedir y que conviene entender:

**Por dónde entró la queja.** Caja, call center o WhatsApp. Parece un dato menor y no lo es:
es lo que después permite saber por qué canal llega el trabajo.

**Qué tipo de solicitud es.** Sin internet, soporte remoto, cambio de plan, cambio de
titular. El tipo cambia lo que el sistema le pide después.

**Qué servicio está afectado.** Internet, televisión o los dos.

**Qué dice el cliente.** Escríbalo con las palabras del cliente, no con las suyas. «Dice que
desde anoche no le carga nada y el módem tiene una luz roja» le sirve al técnico. «Falla de
internet» no le sirve a nadie.

### Lo que el sistema decide solo

**La urgencia no se elige.** Si el servicio está caído, el caso entra como *inmediato* y
tiene cuatro horas hábiles. Todo lo demás entra como *normal* y tiene cinco días hábiles. No
hay una casilla para marcar «urgente», y es a propósito: cuando todo el mundo puede marcar
urgente, en dos semanas todo es urgente y la palabra deja de significar algo.

**El número del caso** se lo pone el sistema y sirve para buscarlo después.

### Si el cliente ya ha llamado antes

Al registrar el caso, el sistema le avisa si ese abonado **ya reportó hace poco**. Vale la
pena que lo diga en la descripción: no es lo mismo una falla nueva que la cuarta vez que
llaman por lo mismo.

---

## Si usted resuelve los casos (técnico de soporte)

### Su bandeja

**Soportes** le muestra los casos. Cada uno tiene un color según cuánto tiempo le queda:

| Color | Qué significa |
|---|---|
| 🟢 Verde | Va con holgura |
| 🟡 Amarillo | Ya consumió más de la mitad del tiempo |
| 🟠 Naranja | Queda poco: vale la pena atenderlo ya |
| 🔴 Rojo | Se pasó del tiempo acordado |
| ⏸️ Gris | El reloj está detenido |

### El reloj y cuándo se detiene

El reloj **solo corre en horario hábil**, de lunes a viernes de 7 a 6, y respeta los festivos
colombianos. Un caso que entra el viernes a las cinco de la tarde no pierde el fin de semana.

Y **se detiene** cuando el caso deja de depender de usted:

- cuando se programa una visita a terreno;
- cuando se escala a ingeniería de redes;
- cuando se marca que el cliente no contesta.

Cuando el caso vuelve a sus manos, el reloj sigue donde se quedó. Eso es deliberado: no se le
puede cobrar a usted un tiempo en el que no podía hacer nada.

### Tomar y trabajar un caso

Abra el caso y **asígneselo**. Eso lo pone *en proceso* y marca que alguien ya lo está
mirando.

Desde ahí puede:

**Resolverlo.** Escoja el diagnóstico —qué era en realidad— y describa qué hizo. El
diagnóstico no es burocracia: es el dato que después dice si el barrio tiene un problema de
red o si son veinte módems viejos.

**Marcar que el cliente no contesta.** El caso se detiene y queda programado un nuevo intento
en cuatro horas hábiles. Usted no tiene que acordarse: el sistema lo devuelve solo a la
bandeja. Al tercer intento fallido se cierra dejando constancia de los tres intentos.

**Mandarlo a terreno.** Se programa una visita con fecha y franja. El caso no se puede cerrar
hasta que la visita ocurra.

**Escalarlo a redes.** Cuando el problema lo supera. El reloj se detiene mientras redes lo
tiene.

### Los que vuelven a llamar

**Clientes → Ver recurrentes** es la lista de los que reportan una y otra vez. Están
separados en dos, porque son dos problemas distintos:

- **Falla recurrente** — hay algo de fondo que nadie ha resuelto. Seguir atendiendo síntomas
  no lo arregla: hay que revisar la instalación.
- **No se logra contactar** — el problema no es la red, es el canal con el cliente. Insistir
  por el mismo medio no va a funcionar; hay que agendar una visita o buscar otro contacto.

### Su informe del mes

**Informes** le muestra el suyo cuando la coordinación lo publica: cuántos casos atendió, en
cuánto tiempo, qué fallas le tocaron y cómo se compara con el promedio del equipo.

Los números quedan **congelados** al publicarse. Si después alguien mueve un caso viejo, su
informe no cambia.

---

## Si usted hace las visitas (técnico de campo)

Para usted hay una pantalla aparte, hecha para el celular: **Mi ruta**.

### Instálela en el teléfono

Ábrala en el navegador del celular y use la opción *Agregar a pantalla de inicio* (o
*Instalar aplicación*). Queda con su propio icono y a pantalla completa, sin la barra del
navegador.

### Su ruta del día

Tres pestañas: **Hoy**, **Mañana** y **Atrasadas**. Cada visita muestra el cliente, la
dirección y lo que reportó, y tiene dos botones grandes: **Llamar** y **Cómo llegar**, que
abren el teléfono y el mapa.

### Cerrar una visita

Toque la visita y elija:

**Se resolvió** — escoja el diagnóstico, describa qué hizo, anote el material que usó, tome
hasta seis fotos y pásele el teléfono al cliente para que firme con el dedo.

**No se pudo** — explique por qué. El caso vuelve a la cola de soporte y el reloj se reanuda.

### Cuando no hay señal

**Cierre la visita igual.** La pantalla guarda todo en el teléfono y lo manda sola cuando
vuelva la señal. No tiene que acordarse ni volver a escribir nada.

La barra de arriba le dice en qué está:

- **fondo oscuro** — hay señal y no hay nada pendiente;
- **fondo naranja** — no hay señal, lo que cierre se guarda en el teléfono;
- **fondo azul** — hay señal y se están enviando los cierres pendientes.

Puede cerrar la aplicación. Lo guardado no se pierde. La única vez que hay que hacer algo es
si la barra dice que la sesión se cerró: ahí toca entrar otra vez y lo pendiente sale solo.

Un detalle: el sistema registra **la hora en que usted cerró la visita**, no la hora en que
llegó al servidor. Si cerró a las diez y sincronizó a las dos, en el sistema queda a las diez.

---

## Si usted coordina (gerente / administrador)

### El panel

**Panel** es el estado del turno ahora mismo: casos abiertos, cuántos van vencidos, cómo está
repartida la carga y qué fallas se están repitiendo.

### Los informes del mes

**Informes → Nuevo informe.** Escoja el técnico y el mes, y el sistema calcula las cifras. Se
revisan, se escribe el comentario y se publica.

**Al publicar, las cifras quedan congeladas.** Un informe firmado no puede cambiar después
porque alguien movió un caso viejo.

### Alertas de vencimiento

El sistema avisa por correo sin que nadie tenga que estar mirando la pantalla:

- al **técnico asignado**, cuando su caso lleva consumido el 85% del tiempo;
- a **coordinación**, cuando un caso ya se venció o cuando lleva rato en riesgo y nadie lo ha
  tomado.

Llega **un solo correo por persona** con todos sus casos, y **máximo dos avisos por caso**:
uno al entrar en riesgo y otro al vencerse. Es a propósito: un correo por caso serían quince
correos al mismo técnico en un día movido, y eso se deja de leer en una semana.

Fuera de horario no se avisa, porque el reloj está detenido.

### Usuarios y bodega

**Usuarios** — crear cuentas, cambiar roles, desactivar a quien ya no está. Desactivar no
borra: el historial de esa persona se conserva.

**Inventario** — el material y su kardex. Cuando un técnico registra material usado en una
visita, se descuenta solo y queda el movimiento.

### Exportar

La bandeja de casos y la lista de recurrentes tienen **Exportar CSV**. Sale exactamente lo
que está viendo, con los filtros puestos, y se abre en Excel con las tildes bien.

---

## Preguntas que salen seguido

**Cerré un caso por error.** Un administrador puede reabrirlo. Queda registrado quién lo
reabrió y cuándo; el historial de un caso no se puede borrar.

**El cliente llamó otra vez por lo mismo.** Se registra como caso nuevo. El sistema lo detecta
solo y lo marca como recurrente — de eso se trata esa lista.

**No me deja cerrar un caso que mandé a terreno.** Correcto. Ese caso se cierra cuando la
visita se hace, desde la pantalla de la visita.

**Se me venció un caso y no era mi culpa.** El reloj se detiene mientras el caso no depende de
usted. Si aun así se venció, quedará la razón en el historial: eso es justamente lo que el
informe del mes sirve para conversar.

**¿Puedo ver los casos de otro técnico?** Los roles de gestión ven todo. El técnico de campo
ve solo sus visitas.
