---
id: memberships.membership
title: Tu ficha de socio y tu cuota
summary: Qué guarda la asociación sobre ti como socio, qué significa cada estado de tu cuota y cómo renovarla.
category: membership
audience: site
order: 10
featured: true
keywords: [socio, socia, socios, ficha de socio, cuota, cuotas, membresía, membresia, renovar, renovación, renovar la cuota, pagar la cuota, número de socio, alta, caduca, caducada, expirada, pendiente, año natural, año académico, curso, asociación, carnet de socio, cuta, pestaña socio, mi situación, situación de socio, cuotas pendientes, cuota atrasada, último pago, estoy al día]
related: [memberships.membership-emails, core.account.complete-profile]
conditions:
  capabilities: [memberships.has_member_record]
  modules: [memberships]
---
Tu cuenta de {{site_name}} está vinculada a una ficha de socio. La ficha y las cuotas las lleva la asociación desde su panel. En la web puedes verlas en la pestaña **Socio** de tu perfil, pero no cambiarlas. Esta guía te explica qué contienen y cómo funcionan.

## La pestaña Socio de tu perfil

En tu perfil, la pestaña **Socio** reúne lo que la asociación tiene sobre ti:

- tu número de socio, tu tipo de socio, el estado de tu ficha y tu fecha de alta;
- tu **Membresía**: la que está en vigor o, si no hay ninguna, la más reciente (por ejemplo, una renovación que todavía no ha empezado), con su estado y sus fechas;
- las **Cuotas pendientes**: lo que te queda por pagar, con su importe y su fecha de vencimiento. Las que ya pasaron esa fecha llevan la marca **Atrasada**, y cuántas son sale junto al nombre de la pestaña. Si no debes nada, pone que estás al día;
- el **Último pago** que consta, con su importe y su fecha.

La pestaña solo enseña los datos. Para corregir algo o saber cómo pagar, usa el enlace **Contactar con la asociación**, al final de la pestaña. Solo la ves si tu cuenta está vinculada a una ficha de socio; los avisos de la asociación sobre tu cuota, en el correo y en la campana, te traen aquí.

## Tu ficha

La ficha guarda tus datos como socio: nombre y apellidos, email, teléfono, dirección, fecha de nacimiento, fecha de alta y tipo de socio (regular, estudiante, senior, honorario o fundador). También tiene un **número de socio** con la forma «2026-0001»: el año del alta y un número de orden.

La ficha no es tu perfil. Cambiar tu nombre o tu email en tu perfil no cambia tu ficha, y al revés.

La ficha tiene uno de estos estados: **Activo**, **Inactivo**, **Suspendido** o **Expulsado**.

## Tu cuota

Cada cuota cubre un periodo, con una fecha de inicio y una fecha de fin. En la pestaña **Socio** aparece como **Membresía**. Según el tipo de periodo, la fecha de fin es:

- **Año natural**: el 31 de diciembre del año en que empieza.
- **Año académico**: el último día del mes anterior al inicio del curso, que elige la asociación (por defecto, el 31 de agosto).
- **Rotativo**: un año después de la fecha de inicio, menos un día.

| Estado | Qué significa |
|---|---|
| **Pendiente** | La asociación la ha creado, pero aún no está en vigor. Por ejemplo, mientras espera tu pago. |
| **Activa** | Está en vigor. |
| **Expirada** | Ya pasó su fecha de fin. La cuota vale hasta el final de su último día, y pasa a expirada esa noche. |
| **Cancelada** | La asociación la ha anulado. |

## El rol {{association_role}}

El rol {{association_role}} es el que distingue en la web a quien es de la asociación. Si la asociación ha activado la asignación automática, tu cuenta lo tiene mientras se cumplan las dos cosas:

- tu ficha está en estado **Activo**, y
- tienes una cuota **Activa**, o eres de un tipo de socio que no necesita cuota (por defecto, los honorarios).

En cuanto deja de cumplirse, por ejemplo porque tu cuota expira o tu ficha pasa a **Suspendido**, el rol se retira solo. Vuelve en cuanto la asociación activa tu nueva cuota. Si la asociación no usa la asignación automática, el rol lo da a mano quien gestiona los roles.

## Renovar tu cuota

La cuota no se renueva desde la web: ponte en contacto con la asociación. En la página **Nosotros** tienes sus datos de contacto y el formulario **Envíanos un mensaje**. La asociación crea tu nueva cuota y la activa cuando corresponde.

## Si algo no sale bien

- **Tus datos de socio no están al día**: cambiarlos en tu perfil no los cambia en la ficha. Pídeselo a la asociación.
- **Has pagado y la pestaña Socio sigue mostrando la cuota pendiente**: la asociación aún no ha anotado el pago. Avísala.
- **Has pagado y no tienes el rol {{association_role}}**: tu nueva cuota puede seguir **Pendiente**. Avisa a la asociación para que la active.

[[action:about|Contactar con la asociación]]
