# Módulo de gestión de socios (GuildForge)

Módulo para GuildForge que gestiona los socios de la asociación, sus cuotas (periodos de membresía) y las tarifas por tipo de socio.

## Características

- **Socios**: alta, edición y baja con datos personales, de contacto, tipo, estado y usuario vinculado.
- **Número de socio**: se genera al crear el socio, con el formato `YYYY-NNNN`.
- **Cuotas**: periodos con fecha de inicio y fin y estado (pendiente, activa, expirada, cancelada). La fecha de fin se calcula a partir del tipo de periodo.
- **Pagos**: lo que cada socio tiene que abonar por sus cuotas (importe y vencimiento) y su cobro (fecha, método y referencia). Pagado, pendiente o vencido desde el día siguiente al vencimiento.
- **Tarifas**: importe por tipo de socio y tipo de periodo, con fechas de validez y una sola tarifa por defecto para cada combinación (marcar otra le quita la marca a la anterior). Proponen el importe de los pagos, sin prorrateo.
- **Expiración diaria**: un comando programado marca las cuotas caducadas, avisa por correo de las que están a punto de caducar y detecta pagos vencidos.
- **Rol de socio** (opcional): asigna el rol de socio del core al usuario vinculado mientras su ficha está activa y tiene una cuota activa o es de un tipo permanente.
- **Socios permanentes**: los tipos marcados (honorario por defecto) no necesitan cuota y quedan fuera de los avisos de caducidad y de las cuotas vencidas.
- **Widgets del dashboard**: estadísticas de socios, cuotas próximas a expirar y cuotas vencidas sin pagar.
- **Pestaña «Socio» de Ajustes de la cuenta**: la cuenta vinculada a una ficha ve su número de socio, tipo, estado y fecha de alta, la membresía en vigor (o la más reciente), las cuotas sin pagar con su vencimiento (las atrasadas marcadas y contadas en la pestaña) y el último pago. Solo lectura; las cuentas sin ficha no ven la pestaña.
- **Guías de ayuda**: guías del panel (categoría propia «Socios y cuotas») y del sitio (categoría «Socios y cuotas» del core, solo para cuentas vinculadas a una ficha, capacidad `memberships.has_member_record`) en `resources/knowledge/es/`, con el botón de ayuda en las páginas del panel del módulo.
- **Años como socio (contrato de actividad del core)**: la fuente `memberships.year` cuenta, para quien construya sobre ella (p. ej. logros), un hecho por cada año natural de la zona de visualización en que la cuenta vinculada a una ficha tuvo una cuota en vigor.
  - Una cuota cuenta si está activa o expirada, desde su primer día y nunca por delante de hoy. Una cuota expirada a mano deja de contar el día en que se expiró (`expired_at`); sin ese día (las filas anteriores), expiró al final de su periodo.
  - Una cuota cancelada no cuenta, aunque estuviera activa antes de cancelarse; las pendientes tampoco. Los pagos tampoco: la cuota se activa a mano, no por cobrar.
  - Un socio de un tipo permanente (honorario por defecto, ajuste del módulo) con la ficha activa cuenta además cada año natural desde el día de su alta, tenga o no cuotas. Al cambiar el ajuste de tipos permanentes se encola un trabajo que vuelve a comunicar, por lotes, los años de los socios activos de los tipos añadidos o quitados.
  - Un sujeto por cuenta y año (`<id de la cuenta>:<año>`), aunque haya varias fichas o cuotas solapadas. Nunca se cuentan fichas sin cuenta ni borradas.
  - Es un dato sensible: solo alimenta recuentos y `describe()` devuelve un título neutro («Socio en 2025») sin enlace, solo a la propia cuenta y a quien puede ver socios.
  - El módulo avisa al core al cambiar una cuota, al vincular, desvincular, borrar o restaurar una ficha y al cambiar su estado, tipo o fecha de alta. El comando diario `memberships:announce-years` avisa de los años que empezaron desde el último día anunciado (guardado en el ajuste `memberships_years_announced_until`), así que un día sin planificador se recupera en la siguiente ejecución.
- **Zonas horarias**: los instantes (alta, pagos, activación) se guardan en la zona de almacenamiento del sitio (UTC cuando el sitio lo usa) y se muestran en la zona de visualización (por defecto Europe/Madrid); las fechas de las cuotas, las tarifas y los vencimientos son días naturales de la zona de visualización, y la cuota de un socio dura hasta el final de su último día allí.

## Requisitos

- GuildForge core `^3.88` (almacenamiento de fechas en UTC y zona horaria de visualización; el rol de socio se elige en el core; base de conocimiento; campana de avisos; contrato de actividad)
- PHP >= 8.5, Laravel >= 13, Filament 5

El core rechaza el módulo si no se cumplen (`module.json` → `requires`).

El comando de expiración lo lanza el planificador de Laravel, así que el servidor debe ejecutar `php artisan schedule:work` (o `schedule:run` cada minuto).

## Instalación

1. Descarga `memberships-X.Y.Z.zip` de la última release de `DavidMRGaona/guildforge-memberships` en GitHub.
2. En el panel, activa el modo mantenimiento (**Configuración del sitio** → pestaña **Mantenimiento**).
3. En **Módulos**, pulsa **Instalar / actualizar** y sube el ZIP.
4. Pulsa **Habilitar** en el módulo: la primera vez ejecuta sus migraciones y seeders.
5. Desactiva el modo mantenimiento.

Las versiones nuevas se aplican desde **Actualizaciones de módulos** (también con el modo mantenimiento activo) o con `php artisan module:update memberships`.

### En desarrollo

Con el repositorio clonado en `src/modules/memberships`:

    php artisan module:discover
    php artisan module:enable memberships

La pestaña de Ajustes de la cuenta es un componente Vue: en desarrollo lo sirve el Vite del core; para probar la build, `php artisan module:build memberships`.

## Configuración

Se configura en el panel, en **Sistema** → **Configuración** → **Socios** (el botón **Configuración** de **Módulos** → **Gestión de socios** lleva a la misma página, pero solo lo ve quien además puede entrar en **Módulos**). Solo la ve quien tiene el permiso `memberships:settings.manage`, para delegar estos ajustes (por ejemplo, a tesorería) sin hacer administrador a nadie; la cuenta también tiene que poder entrar en el panel. Tener solo `modules.configure` no basta. Los valores guardados ahí tienen prioridad sobre `config/memberships.php`, que solo aporta los valores por defecto. `config/settings.php` contiene los valores por defecto con los que se publica el módulo: no lo edites a mano.

| Opción | Descripción | Por defecto |
|--------|-------------|-------------|
| `default_period_type` | Tipo de periodo que propone el formulario de una cuota nueva | `calendar_year` |
| `academic_start_month` | Mes de inicio del año académico | `9` (septiembre) |
| `enable_role_assignment` | Asignar y retirar el rol de socio automáticamente | `false` |
| `permanent_member_types` | Tipos de socio permanentes: con la ficha activa tienen el rol sin cuota y no reciben avisos de caducidad ni de impago | `['honorary']` |
| `expiration_warning_days` | Días antes del fin de una cuota en que se envía el aviso | `30` |
| `send_expired_email` | Avisa al socio cuando su cuota pasa a expirada | `false` |
| `send_overdue_email` | Recuerda al socio, una vez al mes, cada pago vencido sin pagar | `false` |

## Tipos de periodo

| Tipo | Valor | Fecha de fin |
|------|-------|--------------|
| Año natural | `calendar_year` | 31 de diciembre del año de inicio |
| Año académico | `academic_year` | Último día del mes anterior a `academic_start_month` (por ejemplo, 31 de agosto) |
| Rotativo | `rolling` | Un año después de la fecha de inicio, menos un día |

## Estados

Cuotas:

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Creada, todavía no activa |
| Activa | `active` | Vigente; al activarse se guarda `activated_at` |
| Expirada | `expired` | Fecha de fin superada (la marca el comando diario) o puesta a mano |
| Cancelada | `cancelled` | Cancelada a mano; se guarda `cancelled_at` |

Socios (estado de la ficha): activo (`active`), inactivo (`inactive`), suspendido (`suspended`) y expulsado (`expelled`). Solo una ficha activa da el rol de socio.

Tipos de socio: regular (`regular`), estudiante (`student`), senior (`senior`), honorario (`honorary`) y fundador (`founder`).

Métodos de pago: efectivo (`cash`), transferencia (`bank_transfer`), tarjeta (`card`) y otro (`other`).

## Comando de expiración

El módulo programa `memberships:process-expired` cada día a medianoche. El comando:

1. Marca como expiradas las cuotas activas cuya fecha de fin ya ha pasado, también las de los socios permanentes (su rol no depende de ellas).
2. Envía un correo al socio (al usuario vinculado o, si no tiene, al email del socio) cuando a una cuota activa le quedan `expiration_warning_days` días o menos. El aviso se envía una sola vez por cuota y fecha de caducidad (aunque la ventana cruce el 1 de enero) y queda anotado en sus notas como `[EXPIRING_NOTIFIED:AAAA-MM-DD]`, con la fecha de caducidad; al renovar, la nueva fecha vuelve a avisar. Las marcas antiguas por año se siguen respetando. Los socios permanentes no reciben este aviso.
3. Dispara `FeePaymentOverdue` una vez al mes por cada pago vencido sin pagar (anotado como `[OVERDUE_NOTIFIED:AAAA-MM]`), salvo los de socios permanentes. Con `send_overdue_email` activado, el socio recibe un recordatorio con el importe y los días de retraso; no se envía a socios que no estén activos ni por cuotas canceladas.

Para lanzarlo a mano:

    php artisan memberships:process-expired

## Rol de socio

El rol de socio no se configura en el módulo: es el que se elige en el core, en **Configuración del sitio** → **Autenticación** (por defecto `socio`). `member` es el rol de cualquier usuario registrado y el módulo nunca lo asigna ni lo retira, como tampoco `admin` ni `editor`.

Con `enable_role_assignment` activado, el usuario vinculado a un socio tiene el rol de socio si alguna de sus fichas (sin borrar) cumple a la vez:

- la ficha está activa (inactivo, suspendido y expulsado no dan el rol), y
- el socio tiene una cuota activa o es de un tipo permanente (`permanent_member_types`).

El módulo vuelve a comprobarlo en la base de datos y asigna o retira el rol en cuanto cambia algo que lo afecta, desde el panel o desde los servicios del módulo:

- Una cuota se crea, cambia de estado (activa, expirada por el comando diario o a mano, cancelada, pendiente), se borra o pasa a otro socio.
- La ficha cambia de estado o de tipo, se crea, se borra (también definitivamente) o se restaura. Suspender o expulsar retira el rol aunque haya una cuota activa; reactivar la ficha lo devuelve si cumple lo demás.
- La ficha cambia de usuario vinculado o se desvincula: se revisan el usuario anterior y el nuevo.

Un usuario con varias fichas conserva el rol mientras una de ellas cumpla (por ejemplo, una activa con cuota y otra suspendida). Los socios sin usuario vinculado no reciben ningún rol. Los cambios de rol se aplican al momento.

Si el ajuste del core no se puede usar (contiene un rol base) o el rol elegido no existe, el módulo no cambia ningún rol y deja un aviso en el log.

Retirar el rol no distingue cómo se asignó: si un administrador dio el rol de socio a mano a un usuario vinculado a una ficha que no cumple, el siguiente cambio en esa ficha o `memberships:sync-roles` se lo quitan.

### Sincronizar los roles

    php artisan memberships:sync-roles
    php artisan memberships:sync-roles --dry-run

Revisa todos los usuarios vinculados a una ficha (también a fichas borradas), asigna el rol a los que cumplen y lo retira a los demás; los usuarios sin ficha vinculada no se tocan. Termina con «N usuarios revisados, A con rol añadido, R con rol retirado». Es idempotente: una segunda ejecución no cambia nada. Con `--dry-run` solo cuenta lo que haría, sin escribir. No hace nada (y lo indica) mientras `enable_role_assignment` esté desactivado o el rol de socio del core no se pueda usar.

Ejecútalo:

- al activar `enable_role_assignment`: las cuotas y fichas que ya existían no dan el rol por sí solas;
- tras cambiar `permanent_member_types`: el cambio de ajuste no revisa los roles;
- tras actualizar desde una versión que usaba `member_role_name`.

Al desactivar `enable_role_assignment`, el módulo deja de asignar y retirar el rol, pero no quita los roles ya asignados: retíralos desde el editor de roles si hace falta.

Cambiar el rol de socio en el core no retira el anterior a quien ya lo tenía, y `memberships:sync-roles` tampoco lo hace: solo gestiona el rol configurado en ese momento. Retira el anterior desde el editor de roles y ejecuta el comando para asignar el nuevo.

## Importar socios

`MemberImportServiceInterface` da de alta socios a partir de filas ya leídas de una hoja de cálculo (`MemberImportRowDTO`: número de fila y mapa `cabecera → texto`), así que no depende de cómo se lee el fichero. Cada fila es un socio con, si se indica, su periodo actual y la cuota de ese periodo.

- `preview()` dice qué pasaría con cada fila sin escribir nada: `new`, `duplicate` (con el motivo: número o email) o `invalid` (con los mensajes por columna), el número de socio que recibiría y, con `linkByEmail`, la cuenta con la que se vincularía.
- `import()` vuelve a comprobarlo todo y crea las filas nuevas en una sola transacción. No crea nada si no hay ninguna fila nueva, si alguna no es válida, si hay dos cabeceras para la misma columna o si hay más de 1000 filas. Solo corre una importación a la vez: mientras hay otra, devuelve un resultado `busy` que pide reintentar. Si la base de datos rechaza una escritura, lanza `MemberImportFailedException`, que no lleva la consulta ni sus valores, y no queda nada creado.

Reglas:

- **Columnas**: las cabeceras son las de `import.columns` en las traducciones (o su código), sin distinguir mayúsculas, tildes ni espacios de más. Solo `Nombre` y `Apellidos` son obligatorias; las desconocidas se ignoran y se avisan.
- **Valores**: tipos de socio, tipos de periodo y formas de pago por su nombre o su código; fechas `dd/mm/aaaa` o `aaaa-mm-dd`; importes `25`, `25,00` o `25.00`, nunca negativos.
- **Duplicados**: una fila cuyo número o email (sin distinguir mayúsculas) ya tiene un socio, también uno borrado, se salta; nunca se actualiza un socio. Dos filas del mismo fichero con el mismo número o email son las dos inválidas, también cuando una de ellas se salta por duplicada. Importar dos veces el mismo fichero no duplica nada.
- **Número de socio**: si viene con el formato `AAAA-NNNN` se respeta; si no viene, se genera con el año de la fecha de alta, después de los que ya existen y sin chocar con los que da el propio fichero.
- **Periodo**: sin columnas de periodo, el socio entra sin periodo. Las fechas que vienen se guardan tal cual, porque son las reales del socio (a diferencia de `MembershipService::create()`, que lleva el inicio al del tipo de periodo). Con inicio y fin pero sin tipo, el tipo es aquel con el que encajan las fechas (si encajan varios, el predeterminado del módulo, o si no, año natural, académico y rotativo por ese orden); si no encaja ninguno, la fila pide el tipo. Si falta el inicio o el fin, se deduce del tipo (el predeterminado si no se indica). Queda `expired` si su fin ya pasó y `active` si no.
- **Cuota**: necesita periodo. Su moneda es la de la tarifa por defecto del tipo de socio y periodo, como propone el panel, o euros si no hay ninguna. Vence el primer día del periodo si no se indica otra fecha; la forma de pago y la referencia necesitan la fecha de pago.
- **Cuentas**: solo con `linkByEmail` y solo con la única cuenta con ese email (sin distinguir mayúsculas, contando las desactivadas), si está activa, sin anonimizar, con el email verificado y sin socio vinculado. Si hay dudas, la fila entra sin vincular. Tampoco se vincula si la cuenta tiene ya el rol de socio y esta ficha se lo quitaría (sin periodo en curso ni tipo permanente, con la asignación del rol activada); el informe lo explica (`would_remove_association_role`). La cuenta se vincula al final, cuando ya existen el periodo y la cuota, así que el sincronizador de siempre decide el rol una sola vez con el estado final.
- No se envía ningún correo: las cuotas vencidas las detecta después el comando diario. El log solo guarda recuentos, nunca datos de las filas.

## Panel de administración

En el grupo **Personas**:

- **Socios**: listado y ficha del socio; en la ficha, la pestaña **Cuotas** permite crear, editar y borrar sus cuotas, y la pestaña **Pagos** añadir pagos, registrar cobros, corregirlos y borrarlos (con doble confirmación).
- **Tarifas** (debajo de **Socios**): importe por tipo de socio y periodo.

El panel no tiene todavía pantallas para registrar pagos.

## Widgets del dashboard

Solo los ven los usuarios que pueden ver el listado de socios (`memberships:members.view_any`). Se activan, ordenan y limitan en **Panel de control** (configuración del panel).

- **Estadísticas**: total de socios, socios activos, socios con cuota activa y pagos pendientes (sin contar los de socios permanentes).
- **Cuotas próximas a expirar**: cuotas activas que terminan en los próximos 30 días, salvo las de socios permanentes.
- **Cuotas vencidas sin pagar**: pagos con fecha de vencimiento pasada y sin pagar, salvo los de socios permanentes.

## Permisos

Los permisos llevan el prefijo `memberships:` y se asignan a los roles en el editor de roles del panel. Los administradores tienen todos. Ninguno se propone a `editor`, que es el rol de contenido: se dan a los roles de función que cree la asociación (por ejemplo, tesorería o secretaría).

| Permiso | Descripción | Rol por defecto |
|---------|-------------|-----------------|
| `members.view_any` | Ver listado de socios | — |
| `members.view` | Ver socio | — |
| `members.create` | Crear socio | — |
| `members.update` | Editar socio | — |
| `members.delete` | Eliminar socio | — |
| `fee_structures.view_any` | Ver listado de tarifas | — |
| `fee_structures.view` | Ver tarifa | — |
| `fee_structures.create` | Crear tarifa | — |
| `fee_structures.update` | Editar tarifa | — |
| `fee_structures.delete` | Eliminar tarifa | — |
| `fees.view_any` | Ver los pagos de los socios (pestaña **Pagos**) | — |
| `fees.manage` | Gestionar los pagos: añadirlos, registrar cobros, corregirlos y borrarlos | — |
| `settings.manage` | Gestionar la configuración del módulo. Incluye la asignación automática del rol de socio y los tipos permanentes, así que decide quién recibe el rol de socio | — |

## Eventos

| Evento | Cuándo se dispara |
|--------|-------------------|
| `MembershipActivated` | Al crear una cuota activa o pasarla a activa |
| `MembershipDeactivated` | Cuando una cuota activa deja de estarlo (cambio de estado, borrado o cambio de socio) |
| `MembershipExpired` | Al pasar una cuota a expirada, también las de socios permanentes |
| `MembershipExpiring` | Cuando el comando diario envía el aviso de expiración (nunca para socios permanentes) |
| `FeePaymentOverdue` | Cuando el comando diario detecta un pago vencido (nunca para socios permanentes) |

Con `send_expired_email` activado, `MembershipExpired` envía un correo al socio salvo que sea de un tipo permanente, no esté activo o ya tenga una cuota vigente o pendiente que termine hoy o después (ya ha renovado). Los dos avisos están desactivados por defecto; los correos del módulo enlazan con la pestaña «Socio» de Ajustes de la cuenta cuando van a una cuenta, y con «Sobre nosotros» cuando van al email de la ficha (`Notifications/Concerns/LinksToMemberSituation`).

Los avisos de expiración, de cuota caducada y de pago vencido también aparecen en la campana de la web cuando el socio tiene cuenta vinculada (categorías `memberships.expiry-notices` y `memberships.fees` con `site: true`). La campana enlaza con la pestaña «Socio» de Ajustes de la cuenta (`?tab=memberships`), y no se guarda nada para un socio sin cuenta: le llega solo el correo. Requiere core ≥ 3.58.

Las demás clases de `src/Domain/Events` (`MemberCreated`, `MemberStatusChanged`, `MembershipCreated`, `MembershipCancelled`, `FeePaymentRecorded`) no se disparan todavía.

## Estructura del módulo

```
src/modules/memberships/
├── config/
│   ├── memberships.php          # Valores por defecto
│   ├── module.php
│   └── settings.php             # Valores por defecto del formulario del panel
├── database/migrations/
├── lang/{es,en}/                # memberships.php, notifications.php
├── resources/
│   ├── css/module.css           # Utilidades Tailwind de los componentes del módulo
│   ├── js/                      # Pestaña «Socio» de Ajustes de la cuenta, locales y tipos
│   └── knowledge/es/            # Guías de ayuda
├── routes/                      # api.php y web.php (vacíos)
├── src/
│   ├── Application/             # DTOs e interfaces de servicios
│   ├── Console/Commands/        # memberships:process-expired, memberships:sync-roles
│   ├── Domain/                  # Entidades, value objects, enums, eventos
│   ├── Filament/                # Resources, relation manager, widgets, formulario de configuración
│   ├── Infrastructure/          # Modelos, observers (rol de socio), repositorios, servicios (situación del socio para Ajustes de la cuenta)
│   ├── Listeners/               # Aviso de expiración
│   ├── Notifications/
│   └── Policies/
└── tests/
    ├── Unit/
    ├── Integration/
    ├── Feature/
    └── js/                      # Vitest de la pestaña de Ajustes de la cuenta
```

## Tests

Desde `src/` (o dentro del contenedor `guildforge_app`):

    vendor/bin/phpunit -c modules/memberships/phpunit.xml
    vendor/bin/phpunit -c modules/memberships/phpunit.xml --testsuite=Unit

`php artisan test` no ejecuta los tests de los módulos.

## Publicar una versión

1. Sube `version` en `module.json`.
2. Crea y empuja la etiqueta `vX.Y.Z` (debe coincidir con `version`).
3. El workflow `release.yml` ejecuta los tests contra el core (`main`, o la variable `GUILDFORGE_HOST_REF`) y publica `memberships-X.Y.Z.zip` con su `.sha256` en la release.
