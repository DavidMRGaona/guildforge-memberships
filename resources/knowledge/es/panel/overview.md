---
id: memberships.panel.overview
title: 'Socios y cuotas: cómo funcionan'
summary: Qué son las fichas de socio, las cuotas y las tarifas, por qué estados pasan, cómo se relacionan con el rol de socio y quién puede hacer cada cosa.
category: memberships
audience: panel
order: 10
featured: true
keywords: [socios, socio, socia, ficha, fichas, cuota, cuotas, membresía, membresias, tarifa, tarifas, precio, estados, tipos de socio, honorario, fundador, estudiante, número de socio, rol de socio, permisos, tesorería, secretaría, cómo funciona, introducción, empezar]
related: [memberships.panel.manage-members, memberships.panel.memberships, memberships.panel.fee-structures, memberships.panel.expiry-and-notices, memberships.panel.member-role, core.panel.roles-and-permissions]
conditions:
  permissions:
    any: ['memberships:members.view_any', 'memberships:fee_structures.view_any', 'memberships:settings.manage']
---
El módulo de socios lleva el registro de las personas asociadas a {{site_name}}: sus datos, sus cuotas y las tarifas. Esta guía explica las piezas y cómo encajan. Las demás guías de socios entran en el detalle de cada tarea.

## Las piezas

| Pieza | Qué es |
|---|---|
| **Socio** | La ficha de una persona asociada: datos personales y de contacto, tipo de socio, estado y fecha de alta. Al crearla recibe un **Número de socio** con la forma «2026-0001». Puede estar vinculada a una cuenta de la web con **Usuario vinculado**. |
| **Cuota** | Un periodo de pertenencia de un socio, con fecha de inicio, fecha de fin y estado. Para renovar, se crea la cuota del periodo siguiente. Se gestionan dentro de la ficha, en la pestaña **Cuotas**. |
| **Tarifa** | El importe que corresponde a un tipo de socio y un tipo de periodo, con sus fechas de validez. |

## Tipos de socio

**Regular**, **Estudiante**, **Senior**, **Honorario** y **Fundador**. Algunos tipos pueden ser **permanentes**: con la ficha activa, cuentan como socios sin necesitar cuota y no reciben avisos de caducidad ni de impago. Por defecto solo lo es **Honorario**; se cambia en la configuración del módulo.

## Estados de la ficha

| Estado | Qué significa |
|---|---|
| **Activo** | Es socio de pleno derecho. Es el único estado que da el rol de socio. |
| **Inactivo** | Ha dejado la asociación, o la ficha está parada. |
| **Suspendido** | Tiene la pertenencia suspendida. Pierde el rol de socio aunque tenga una cuota activa. |
| **Expulsado** | Ha sido expulsado. Pierde el rol de socio. |

## Estados de una cuota

| Estado | Qué significa |
|---|---|
| **Pendiente** | Creada, pero aún no en vigor. Es el estado con que empieza una cuota nueva. |
| **Activa** | En vigor. Al activarla se guarda la fecha en **Activada**. |
| **Expirada** | Ya pasó su fecha de fin. Cada noche, las cuotas activas cuya fecha de fin ya ha pasado pasan solas a este estado. También puedes ponerlo a mano. |
| **Cancelada** | Anulada a mano. |

## El rol de socio

El rol {{association_role}} es el que distingue en la web a quien es de la asociación. Se elige en la configuración del sitio. Si en la configuración del módulo está activado **Asignar rol automáticamente**, el módulo lo da y lo quita solo: la cuenta vinculada lo tiene mientras su ficha está **Activo** y tiene una cuota **Activa** o es de un tipo permanente. La guía del rol de socio lo explica en detalle.

## Quién hace qué

Ninguno de estos permisos se concede solo, tampoco al rol de edición: se dan a cada rol en **Roles**, por ejemplo a uno de tesorería o de secretaría. La administración los tiene todos.

| Permiso | Qué permite |
|---|---|
| **Ver listado de socios** | Ver la lista de socios y los resúmenes de socios del escritorio |
| **Ver socio** | Abrir la ficha de un socio en modo lectura |
| **Crear socio** | Dar de alta socios |
| **Editar socio** | Cambiar fichas y gestionar sus cuotas |
| **Eliminar socio** | Borrar socios |
| **Ver listado de tarifas** y **Ver tarifa** | Ver las tarifas |
| **Crear tarifa**, **Editar tarifa** y **Eliminar tarifa** | Cambiar las tarifas |
| **Ver los pagos de los socios** | Ver la pestaña **Pagos** de cada socio |
| **Gestionar los pagos de los socios** | Añadir pagos, registrar cobros, corregirlos y borrarlos |
| **Gestionar la configuración del módulo** | Cambiar la configuración del módulo. Incluye la asignación automática del rol de socio y los tipos permanentes, así que decide quién recibe el rol |

## Dónde está cada cosa

- En el menú lateral, el grupo **Gestión de socios** tiene **Socios**, **Tarifas** y **Configuración**.
- Dentro de un socio, las pestañas **Datos del socio**, **Cuotas** y **Pagos**.
- En el escritorio, los resúmenes **Total de socios**, **Socios activos**, **Con cuota activa** y **Pagos pendientes**, y las tablas **Cuotas próximas a expirar** y **Cuotas vencidas sin pagar**.


[[action:filament.admin.resources.members.index|Ver los socios|if=memberships:members.view_any]]
[[action:filament.admin.resources.fee-structures.index|Ver las tarifas|if=memberships:fee_structures.view_any]]
