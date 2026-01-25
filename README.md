# Módulo de gestión de socios (GuildForge)

Módulo para GuildForge que permite gestionar socios, cuotas y tarifas.

## Características

- **Gestión de socios**: Alta, baja y modificación de socios con datos personales y de contacto
- **Cuotas**: Control de cuotas con fechas de inicio/fin y estados (pendiente, activa, expirada, cancelada)
- **Tarifas**: Configuración de tarifas por tipo de socio y periodo
- **Cálculo automático**: Fecha de fin calculada según tipo de periodo (año natural, académico o rotativo)
- **Expiración automática**: Comando programado para marcar cuotas expiradas y enviar notificaciones
- **Número de socio**: Generación automática en formato `YYYY-NNNN`

## Instalación

El módulo se activa automáticamente al estar en `src/modules/memberships/`.

```bash
# Ejecutar migraciones
php artisan migrate

# Verificar que el módulo está activo
php artisan module:list
```

## Configuración

El módulo se configura desde el panel de administración en **Gestión de socios > Configuración** o editando `config/memberships.php`:

| Opción | Descripción | Por defecto |
|--------|-------------|-------------|
| `default_period_type` | Tipo de periodo por defecto | `calendar_year` |
| `academic_start_month` | Mes de inicio del año académico | `9` (septiembre) |
| `enable_role_assignment` | Asignar rol automáticamente al activar cuota | `false` |
| `member_role_name` | Nombre del rol a asignar | `member` |
| `grace_period_days` | Días de gracia antes de expirar | `30` |
| `expiration_warning_days` | Días de aviso antes de expiración | `30` |
| `enable_proration` | Habilitar prorrateo de cuotas | `true` |
| `default_currency` | Moneda por defecto | `EUR` |

## Tipos de periodo

| Tipo | Descripción |
|------|-------------|
| **Año natural** | Del 1 de enero al 31 de diciembre |
| **Año académico** | Del mes configurado hasta el mes anterior del año siguiente (ej: sept-agosto) |
| **Rotativo** | 12 meses desde la fecha de inicio |

## Estados de cuota

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Cuota creada, pendiente de activación |
| **Activa** | Cuota activa y vigente |
| **Expirada** | Fecha de fin superada |
| **Cancelada** | Cancelada manualmente |

Las fechas `activated_at` y `cancelled_at` se establecen automáticamente al cambiar el estado.

## Tipos de socio

- Regular
- Estudiante
- Senior
- Honorario
- Fundador

## Comando de expiración

El comando `memberships:process-expired` se ejecuta diariamente y:

1. Marca como expiradas las cuotas con fecha de fin pasada
2. Envía notificaciones de cuotas próximas a expirar
3. Procesa cuotas con pagos vencidos

```bash
# Ejecutar manualmente
php artisan memberships:process-expired
```

## Estructura del módulo

```
src/modules/memberships/
├── config/
│   └── memberships.php          # Configuración del módulo
├── database/migrations/         # Migraciones
├── lang/
│   ├── en/memberships.php       # Traducciones inglés
│   └── es/memberships.php       # Traducciones español
├── src/
│   ├── Application/             # DTOs, interfaces de servicios
│   ├── Console/Commands/        # Comandos Artisan
│   ├── Domain/                  # Entidades, VOs, enums, eventos
│   ├── Filament/                # Resources, pages, widgets
│   ├── Infrastructure/          # Repositorios, servicios, modelos
│   ├── Listeners/               # Event listeners
│   ├── Notifications/           # Notificaciones
│   └── Policies/                # Políticas de acceso
└── tests/                       # Tests unitarios e integración
```

## Permisos

| Permiso | Descripción |
|---------|-------------|
| `members.view_any` | Ver listado de socios |
| `members.view` | Ver socio |
| `members.create` | Crear socio |
| `members.update` | Editar socio |
| `members.delete` | Eliminar socio |
| `fee_structures.view_any` | Ver listado de tarifas |
| `fee_structures.view` | Ver tarifa |
| `fee_structures.create` | Crear tarifa |
| `fee_structures.update` | Editar tarifa |
| `fee_structures.delete` | Eliminar tarifa |

## Panel de administración

El módulo añade las siguientes secciones al panel:

- **Gestión de socios**
  - Socios: CRUD de socios con tabs para datos y cuotas
  - Tarifas: Configuración de tarifas por tipo de socio
  - Configuración: Ajustes del módulo

## Widgets del dashboard

- **Estadísticas**: Total de socios, socios activos, con cuota activa, pagos pendientes
- **Cuotas próximas a expirar**: Lista de cuotas que expiran pronto
- **Cuotas vencidas sin pagar**: Lista de cuotas con pagos pendientes

## Eventos

| Evento | Cuándo se dispara |
|--------|-------------------|
| `MemberCreated` | Al crear un socio |
| `MemberStatusChanged` | Al cambiar estado del socio |
| `MembershipCreated` | Al crear una cuota |
| `MembershipActivated` | Al activar una cuota |
| `MembershipExpired` | Al expirar una cuota |
| `MembershipExpiring` | Días antes de expirar |
| `MembershipCancelled` | Al cancelar una cuota |
| `FeePaymentRecorded` | Al registrar un pago |
| `FeePaymentOverdue` | Al vencer un pago |

## Integración con roles

Si `enable_role_assignment` está activado:
- Al activar una cuota, se asigna el rol configurado al usuario vinculado
- Al expirar la cuota, se revoca el rol

## Tests

```bash
# Ejecutar tests del módulo
php artisan test --filter=Memberships
```
