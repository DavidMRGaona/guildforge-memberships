# Módulo de gestión de socios (GuildForge)

Módulo para GuildForge que gestiona los socios de la asociación, sus cuotas (periodos de membresía) y las tarifas por tipo de socio.

## Características

- **Socios**: alta, edición y baja con datos personales, de contacto, tipo, estado y usuario vinculado.
- **Número de socio**: se genera al crear el socio, con el formato `YYYY-NNNN`.
- **Cuotas**: periodos con fecha de inicio y fin y estado (pendiente, activa, expirada, cancelada). La fecha de fin se calcula a partir del tipo de periodo.
- **Tarifas**: importe por tipo de socio y tipo de periodo, con fechas de validez.
- **Expiración diaria**: un comando programado marca las cuotas caducadas, avisa por correo de las que están a punto de caducar y detecta pagos vencidos.
- **Rol de socio** (opcional): asigna el rol de socio del core al usuario vinculado mientras su ficha está activa y tiene una cuota activa o es de un tipo permanente.
- **Socios permanentes**: los tipos marcados (honorario por defecto) no necesitan cuota y quedan fuera de los avisos de caducidad y de las cuotas vencidas.
- **Widgets del dashboard**: estadísticas de socios, cuotas próximas a expirar y cuotas vencidas sin pagar.

## Requisitos

- GuildForge core `^3.8` (el rol de socio se elige en el core; desde la 3.6, la configuración guardada en el panel se conserva al actualizar los módulos)
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

El módulo no tiene componentes Vue, así que no necesita `module:build`.

## Configuración

Se configura en el panel, en **Módulos** → **Gestión de socios** → **Configurar**. Los valores guardados ahí tienen prioridad sobre `config/memberships.php`, que solo aporta los valores por defecto. `config/settings.php` contiene los valores por defecto con los que se publica el módulo: no lo edites a mano.

| Opción | Descripción | Por defecto |
|--------|-------------|-------------|
| `default_period_type` | Tipo de periodo que propone el formulario de una cuota nueva | `calendar_year` |
| `academic_start_month` | Mes de inicio del año académico | `9` (septiembre) |
| `enable_role_assignment` | Asignar y retirar el rol de socio automáticamente | `false` |
| `permanent_member_types` | Tipos de socio permanentes: con la ficha activa tienen el rol sin cuota y no reciben avisos de caducidad ni de impago | `['honorary']` |
| `expiration_warning_days` | Días antes del fin de una cuota en que se envía el aviso | `30` |

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
2. Envía un correo al socio (al usuario vinculado o, si no tiene, al email del socio) cuando a una cuota activa le quedan `expiration_warning_days` días o menos. El aviso se envía una vez al año por cuota y queda anotado en sus notas como `[EXPIRING_NOTIFIED:AAAA]`. Los socios permanentes no reciben este aviso.
3. Dispara `FeePaymentOverdue` una vez al mes por cada pago vencido sin pagar (anotado como `[OVERDUE_NOTIFIED:AAAA-MM]`), salvo los de socios permanentes. El módulo no incluye ninguna notificación para este evento.

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

## Panel de administración

En el grupo **Gestión de socios**:

- **Socios**: listado y ficha del socio; en la ficha, la pestaña **Cuotas** permite crear, editar y borrar sus cuotas.
- **Tarifas**: importe por tipo de socio y periodo.

El panel no tiene todavía pantallas para registrar pagos.

## Widgets del dashboard

Solo los ven los usuarios que pueden ver el listado de socios (`memberships:members.view_any`). Se activan, ordenan y limitan en **Panel de control** (configuración del panel).

- **Estadísticas**: total de socios, socios activos, socios con cuota activa y pagos pendientes (sin contar los de socios permanentes).
- **Cuotas próximas a expirar**: cuotas activas que terminan en los próximos 30 días, salvo las de socios permanentes.
- **Cuotas vencidas sin pagar**: pagos con fecha de vencimiento pasada y sin pagar, salvo los de socios permanentes.

## Permisos

Los permisos llevan el prefijo `memberships:` y se asignan a los roles en el editor de roles del panel. Los administradores tienen todos.

| Permiso | Descripción | Rol por defecto |
|---------|-------------|-----------------|
| `members.view_any` | Ver listado de socios | editor |
| `members.view` | Ver socio | editor |
| `members.create` | Crear socio | editor |
| `members.update` | Editar socio | editor |
| `members.delete` | Eliminar socio | — |
| `fee_structures.view_any` | Ver listado de tarifas | editor |
| `fee_structures.view` | Ver tarifa | editor |
| `fee_structures.create` | Crear tarifa | — |
| `fee_structures.update` | Editar tarifa | — |
| `fee_structures.delete` | Eliminar tarifa | — |

## Eventos

| Evento | Cuándo se dispara |
|--------|-------------------|
| `MembershipActivated` | Al crear una cuota activa o pasarla a activa |
| `MembershipDeactivated` | Cuando una cuota activa deja de estarlo (cambio de estado, borrado o cambio de socio) |
| `MembershipExpired` | Al pasar una cuota a expirada, también las de socios permanentes |
| `MembershipExpiring` | Cuando el comando diario envía el aviso de expiración (nunca para socios permanentes) |
| `FeePaymentOverdue` | Cuando el comando diario detecta un pago vencido (nunca para socios permanentes) |

El módulo no escucha `MembershipExpired`. Un listener que notifique al socio con este evento debe excluir los tipos permanentes (`permanent_member_types`), igual que hacen el aviso de caducidad y `FeePaymentOverdue`.

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
├── routes/                      # api.php y web.php (vacíos)
├── src/
│   ├── Application/             # DTOs e interfaces de servicios
│   ├── Console/Commands/        # memberships:process-expired, memberships:sync-roles
│   ├── Domain/                  # Entidades, value objects, enums, eventos
│   ├── Filament/                # Resources, relation manager, widgets, formulario de configuración
│   ├── Infrastructure/          # Modelos, observers (rol de socio), repositorios, servicios
│   ├── Listeners/               # Aviso de expiración
│   ├── Notifications/
│   └── Policies/
└── tests/
    ├── Unit/
    ├── Integration/
    └── Feature/
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
