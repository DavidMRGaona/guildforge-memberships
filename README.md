# Módulo de gestión de socios (GuildForge)

Módulo para GuildForge que gestiona los socios de la asociación, sus cuotas (periodos de membresía) y las tarifas por tipo de socio.

## Características

- **Socios**: alta, edición y baja con datos personales, de contacto, tipo, estado y usuario vinculado.
- **Número de socio**: se genera al crear el socio, con el formato `YYYY-NNNN`.
- **Cuotas**: periodos con fecha de inicio y fin y estado (pendiente, activa, expirada, cancelada). La fecha de fin se calcula a partir del tipo de periodo.
- **Tarifas**: importe por tipo de socio y tipo de periodo, con fechas de validez.
- **Expiración diaria**: un comando programado marca las cuotas caducadas, avisa por correo de las que están a punto de caducar y detecta pagos vencidos.
- **Rol de socio** (opcional): asigna un rol al usuario vinculado mientras tiene una cuota activa.
- **Widgets del dashboard**: estadísticas de socios, cuotas próximas a expirar y cuotas vencidas sin pagar.

## Requisitos

- GuildForge core `^3.0`
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

Se configura en el panel, en **Módulos** → **Gestión de socios** → **Configurar**. Los valores guardados ahí tienen prioridad sobre `config/memberships.php`, que solo aporta los valores por defecto. `config/settings.php` es el fichero que escribe el panel y se publica con los valores por defecto: no lo edites a mano. Comprueba la configuración después de actualizar el módulo.

| Opción | Descripción | Por defecto |
|--------|-------------|-------------|
| `default_period_type` | Tipo de periodo que propone el formulario de una cuota nueva | `calendar_year` |
| `academic_start_month` | Mes de inicio del año académico | `9` (septiembre) |
| `enable_role_assignment` | Asignar y retirar el rol de socio automáticamente | `false` |
| `member_role_name` | Nombre del rol que se asigna | `member` |
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

Socios: activo (`active`), inactivo (`inactive`), suspendido (`suspended`) y expulsado (`expelled`).

Tipos de socio: regular, estudiante, senior, honorario y fundador.

Métodos de pago: efectivo (`cash`), transferencia (`bank_transfer`), tarjeta (`card`) y otro (`other`).

## Comando de expiración

El módulo programa `memberships:process-expired` cada día a medianoche. El comando:

1. Marca como expiradas las cuotas activas cuya fecha de fin ya ha pasado.
2. Envía un correo al socio (al usuario vinculado o, si no tiene, al email del socio) cuando a una cuota activa le quedan `expiration_warning_days` días o menos. El aviso se envía una vez al año por cuota y queda anotado en sus notas como `[EXPIRING_NOTIFIED:AAAA]`.
3. Dispara `FeePaymentOverdue` una vez al mes por cada pago vencido sin pagar (anotado como `[OVERDUE_NOTIFIED:AAAA-MM]`). El módulo no incluye ninguna notificación para este evento.

Para lanzarlo a mano:

    php artisan memberships:process-expired

## Rol de socio

Con `enable_role_assignment` activado:

- Cuando una cuota se crea como activa o pasa a activa (desde la ficha del socio en el panel o por código), el usuario vinculado al socio recibe el rol `member_role_name`.
- Cuando una cuota pasa a expirada (por el comando diario o a mano) y el socio no tiene otra cuota activa, se le retira el rol.

El rol debe existir; por defecto es `member`, el rol de socio del core. Si no existe, no se asigna nada. Cancelar o borrar una cuota no retira el rol. Los socios sin usuario vinculado no reciben ningún rol.

Los módulos que deciden quién es socio por el rol `member` no siguen un cambio de `member_role_name`.

## Panel de administración

En el grupo **Gestión de socios**:

- **Socios**: listado y ficha del socio; en la ficha, la pestaña **Cuotas** permite crear, editar y borrar sus cuotas.
- **Tarifas**: importe por tipo de socio y periodo.

El panel no tiene todavía pantallas para registrar pagos.

## Widgets del dashboard

Solo los ven los usuarios que pueden ver el listado de socios (`memberships:members.view_any`). Se activan, ordenan y limitan en **Panel de control** (configuración del panel).

- **Estadísticas**: total de socios, socios activos, socios con cuota activa y pagos pendientes.
- **Cuotas próximas a expirar**: cuotas activas que terminan en los próximos 30 días.
- **Cuotas vencidas sin pagar**: pagos con fecha de vencimiento pasada y sin pagar.

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
| `MembershipExpired` | Al pasar una cuota a expirada |
| `MembershipExpiring` | Cuando el comando diario envía el aviso de expiración |
| `FeePaymentOverdue` | Cuando el comando diario detecta un pago vencido |

Las demás clases de `src/Domain/Events` (`MemberCreated`, `MemberStatusChanged`, `MembershipCreated`, `MembershipCancelled`, `FeePaymentRecorded`) no se disparan todavía.

## Estructura del módulo

```
src/modules/memberships/
├── config/
│   ├── memberships.php          # Valores por defecto
│   ├── module.php
│   └── settings.php             # Configuración guardada desde el panel
├── database/migrations/
├── lang/{es,en}/                # memberships.php, notifications.php
├── routes/                      # api.php y web.php (vacíos)
├── src/
│   ├── Application/             # DTOs e interfaces de servicios
│   ├── Console/Commands/        # memberships:process-expired
│   ├── Domain/                  # Entidades, value objects, enums, eventos
│   ├── Filament/                # Resources, relation manager, widgets, formulario de configuración
│   ├── Infrastructure/          # Modelos, observer, repositorios, servicios
│   ├── Listeners/               # Rol de socio y aviso de expiración
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
