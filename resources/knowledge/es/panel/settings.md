---
id: memberships.panel.settings
title: 'Configuración de socios: periodos, rol y avisos'
summary: Qué decide cada campo de la configuración del módulo de socios y a qué afecta.
category: memberships
audience: panel
order: 70
keywords: [configuración, ajustes, configurar socios, tipo de periodo, periodo predeterminado, año académico, mes de inicio, curso, rol de socio, asignar rol, socios permanentes, honorario, días de aviso, avisos, correos, notificaciones, membresía expirada, cuotas atrasadas, impago]
related: [memberships.panel.member-role, memberships.panel.expiry-and-notices, memberships.panel.memberships]
conditions:
  permissions:
    all: ['memberships:settings.manage']
---
La configuración del módulo decide cómo se calculan las cuotas, quién recibe el rol de socio y qué correos salen.

## Abrir la configuración

En el menú lateral, abre **Sistema**, elige **Configuración** y después **Socios**. La página se llama **Configuración del módulo**. Al terminar, pulsa **Guardar**.

> [!IMPORTANT]
> El permiso **Gestionar la configuración del módulo** decide quién recibe el rol de socio, porque incluye la asignación automática y los tipos permanentes. Dalo solo a quien deba decidirlo.

## Configuración de periodo de cuota

- **Tipo de periodo predeterminado**: el tipo con que empieza cada cuota nueva (**Año natural** por defecto). Cada cuota puede elegir otro.
- **Mes de inicio del año académico**: el mes en que empieza el curso (septiembre por defecto). Solo cuenta en las cuotas de **Año académico**, que acaban el último día del mes anterior.

Cambiar estos campos no toca las cuotas que ya existen.

## Integración con roles

**Asignar rol automáticamente**: viene desactivado. Activado, la cuenta vinculada a un socio tiene el rol {{association_role}} mientras su ficha está en **Activo** y tiene una cuota **Activa** o es de un tipo permanente, y lo pierde cuando deja de cumplirlo. La guía del rol de socio explica cuándo se revisa y qué hay que hacer al activarlo.

## Socios permanentes

**Tipos de socio permanentes**: los socios de los tipos marcados (por defecto, **Honorario**), con la ficha en **Activo**, tienen el rol sin necesitar cuota y no salen en los avisos de caducidad ni en las cuotas vencidas. Cambiar la lista no revisa los roles que ya hay.

## Notificaciones

- **Días de aviso antes de expiración**: cuántos días antes del fin de una cuota activa se envía el correo «Tu membresía está a punto de expirar», de 0 a 365 (30 por defecto). Este correo no se puede apagar aquí, pero cada socio con cuenta puede darse de baja desde su perfil.
- **Avisar de la membresía expirada**: envía un correo al socio cuando su cuota expira, salvo que ya la haya renovado. Viene desactivado, y solo afecta a las cuotas que expiren después de activarlo.
- **Avisar de las cuotas atrasadas**: envía un recordatorio al mes por cada pago vencido sin pagar, también los antiguos. Viene desactivado.

> [!WARNING]
> Antes de activar **Avisar de las cuotas atrasadas**, comprueba que los pagos ya cobrados estén marcados como pagados: si no, los socios recibirán recordatorios de lo que ya pagaron.

## También desde Módulos

La administración puede abrir el mismo formulario en **Módulos**, con **Configurar** en **Gestión de socios**. Los valores son los mismos en las dos páginas.

## Si algo no sale bien

- **No ves Socios en Sistema → Configuración**: necesitas el permiso **Gestionar la configuración del módulo**.
- **Has cambiado los tipos permanentes y los roles siguen igual**: hay que revisar los roles de todos con `memberships:sync-roles`. La guía del rol de socio lo explica.

[[action:filament.admin.configuracion.pages.membership-settings|Abrir la configuración de socios]]
