---
id: memberships.panel.expiry-and-notices
title: Caducidad de las cuotas y avisos a los socios
summary: Qué hace cada noche el módulo con las cuotas, qué correos reciben los socios y cómo seguir desde el escritorio las cuotas que caducan.
category: memberships
audience: panel
order: 50
keywords: [caducidad, caducar, caduca, expirar, expirada, vencer, vencida, aviso, avisos, correo, correos, email, recordatorio, notificación, impago, cuota atrasada, pago pendiente, pagos pendientes, escritorio, widget, resumen, próximas a expirar, vencidas sin pagar, renovaciones, cada noche]
related: [memberships.panel.memberships, memberships.panel.payments, memberships.panel.settings, memberships.panel.overview, core.panel.dashboard-settings]
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
| **Tu membresía está a punto de expirar** | Cuando a una cuota activa le quedan los días de aviso o menos. | Cada socio con cuenta puede darse de baja de este aviso en la pestaña **Correos** de su perfil. Tú puedes cambiar los días de aviso (con 0 no sale). |
| **Tu membresía ha expirado** | Cuando una cuota pasa a **Expirada**, cada noche o a mano. No sale si la ficha no está en **Activo** ni si el socio ya tiene otra cuota pendiente o activa que no ha terminado. | Sí: **Avisar de la membresía expirada**, desactivado por defecto. El socio no puede darse de baja. |
| **Cuota pendiente de pago** | Una vez al mes por cada pago vencido sin pagar. No sale si la ficha no está en **Activo** ni si la cuota está cancelada. | Sí: **Avisar de las cuotas atrasadas**, desactivado por defecto. El socio no puede darse de baja. |

Los correos van al email de la cuenta vinculada al socio o, si no tiene, al **Email** de su ficha. Si no tiene ninguno de los dos, no se envían. Si el socio tiene cuenta, el correo trae el botón **Ver tu situación de socio**, que abre la pestaña **Socio** de su perfil, con su membresía, sus cuotas pendientes y su último pago; el aviso de la campana lleva al mismo sitio. Si el correo va al **Email** de la ficha, el botón es **Contactar con la asociación** y abre la página **Nosotros** de la web.

Cada socio con cuenta elige qué correos recibe en la pestaña **Correos** de su perfil. El aviso de que la membresía va a expirar es optativo: si se da de baja, no le llega, y el correo trae un enlace para hacerlo con un clic. Los de cuota expirada y pago pendiente son obligatorios y siempre salen si tú los tienes activados. Los ajustes del módulo mandan antes: lo que apagas aquí no sale aunque el socio lo tenga activado. Los socios sin cuenta, que reciben el correo en el **Email** de su ficha, no pueden elegir.

## El escritorio

Quien tiene **Ver listado de socios** ve en el escritorio:

- Los resúmenes **Total de socios**, **Socios activos**, **Con cuota activa** y **Pagos pendientes** (los pagos sin pagar, sin contar los de socios permanentes).
- **Cuotas próximas a expirar**: las cuotas activas que terminan en los próximos 30 días, con los **Días restantes** y el botón **Ver socio** para ir a su ficha.
- **Cuotas vencidas sin pagar**: los pagos con el vencimiento pasado, con el **Importe** y los **Días de retraso**.

Las dos tablas no incluyen a los socios permanentes. Se activan, se ordenan y se limitan en la configuración del escritorio.

Los pagos se añaden y se cobran en la pestaña **Pagos** de la ficha de cada socio.

## Si algo no sale bien

- **Un socio no ha recibido el aviso de caducidad**: comprueba que su cuota está **Activa** (las pendientes no avisan), que su tipo no es permanente y que tiene una cuenta vinculada o un **Email** en la ficha. Si tiene cuenta, comprueba también que no se ha dado de baja de los avisos en su perfil, o que no tiene los correos en pausa.
- **Una cuota no ha pasado a Expirada**: pasa la noche siguiente a su fecha de fin. Si sigue igual días después, avisa a quien mantiene la instalación: la revisión nocturna depende de su planificador de tareas.
