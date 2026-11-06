---
id: memberships.panel.expiry-and-notices
title: Caducidad de las cuotas y avisos a los socios
summary: Qué hace cada noche el módulo con las cuotas, qué correos reciben los socios y cómo seguir desde el escritorio las cuotas que caducan.
category: memberships
audience: panel
order: 50
keywords: [caducidad, caducar, caduca, expirar, expirada, vencer, vencida, aviso, avisos, correo, correos, email, recordatorio, notificación, impago, cuota atrasada, pago pendiente, pagos pendientes, escritorio, widget, resumen, próximas a expirar, vencidas sin pagar, renovaciones, cada noche]
related: [memberships.panel.memberships, memberships.panel.settings, memberships.panel.overview, core.panel.dashboard-settings]
conditions:
  permissions:
    any: ['memberships:members.view_any', 'memberships:settings.manage']
---
El módulo revisa las cuotas cada noche: marca las que han expirado, avisa a los socios cuyas cuotas están a punto de expirar y recuerda los pagos atrasados. El escritorio te enseña lo que tienes pendiente.

## Lo que pasa cada noche

1. Las cuotas **Activa** cuya fecha de fin ya ha pasado pasan a **Expirada**. También las de los socios de un tipo permanente, cuyo rol no depende de ellas.
2. A los socios cuya cuota activa termina dentro de los **Días de aviso antes de expiración** de la configuración (30 por defecto) les llega el correo «Tu membresía está a punto de expirar». Va una vez por cuota y año natural, y queda anotado en las **Notas** de la cuota.
3. Cada pago con la fecha de vencimiento pasada y sin pagar se revisa una vez al mes. Si está activado **Avisar de las cuotas atrasadas**, el socio recibe el correo «Cuota pendiente de pago».

Los socios de un tipo permanente (por defecto, **Honorario**) nunca reciben avisos de caducidad ni de impago.

## Los correos

| Correo | Cuándo sale | Se puede apagar |
|---|---|---|
| **Tu membresía está a punto de expirar** | Cuando a una cuota activa le quedan los días de aviso o menos. | No, pero puedes cambiar los días de aviso. |
| **Tu membresía ha expirado** | Cuando una cuota pasa a **Expirada**, cada noche o a mano. No sale si la ficha no está en **Activo** ni si el socio ya tiene otra cuota pendiente o activa que no ha terminado. | Sí: **Avisar de la membresía expirada**, desactivado por defecto. |
| **Cuota pendiente de pago** | Una vez al mes por cada pago vencido sin pagar. No sale si la ficha no está en **Activo** ni si la cuota está cancelada. | Sí: **Avisar de las cuotas atrasadas**, desactivado por defecto. |

Los correos van al email de la cuenta vinculada al socio o, si no tiene, al **Email** de su ficha. Si no tiene ninguno de los dos, no se envían. Todos traen el botón **Contactar con la asociación**, que abre la página **Nosotros** de la web.

## El escritorio

Quien tiene **Ver listado de socios** ve en el escritorio:

- Los resúmenes **Total de socios**, **Socios activos**, **Con cuota activa** y **Pagos pendientes** (los pagos sin pagar, sin contar los de socios permanentes).
- **Cuotas próximas a expirar**: las cuotas activas que terminan en los próximos 30 días, con los **Días restantes** y el botón **Ver socio** para ir a su ficha.
- **Cuotas vencidas sin pagar**: los pagos con el vencimiento pasado, con el **Importe** y los **Días de retraso**.

Las dos tablas no incluyen a los socios permanentes. Se activan, se ordenan y se limitan en la configuración del escritorio.

> [!IMPORTANT]
> El panel todavía no tiene pantallas para registrar pagos, así que **Pagos pendientes** y **Cuotas vencidas sin pagar** solo muestran pagos que se hayan registrado por otra vía.

## Si algo no sale bien

- **Un socio no ha recibido el aviso de caducidad**: comprueba que su cuota está **Activa** (las pendientes no avisan), que su tipo no es permanente y que tiene una cuenta vinculada o un **Email** en la ficha.
- **Una cuota no ha pasado a Expirada**: pasa la noche siguiente a su fecha de fin. Si sigue igual días después, avisa a quien mantiene la instalación: la revisión nocturna depende de su planificador de tareas.
