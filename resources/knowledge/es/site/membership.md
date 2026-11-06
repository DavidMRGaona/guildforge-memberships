---
id: memberships.membership
title: Tu ficha de socio y tu cuota
summary: Qué guarda la asociación sobre ti como socio, qué significa cada estado de tu cuota y cómo renovarla.
category: membership
audience: site
order: 10
featured: true
keywords: [socio, socia, socios, ficha de socio, cuota, cuotas, membresía, membresia, renovar, renovación, renovar la cuota, pagar la cuota, número de socio, alta, caduca, caducada, expirada, pendiente, año natural, año académico, curso, asociación, carnet de socio, cuta]
related: [memberships.membership-emails, core.account.complete-profile]
conditions:
  capabilities: [memberships.has_member_record]
  modules: [memberships]
---
Tu cuenta de {{site_name}} está vinculada a una ficha de socio. La ficha y las cuotas las lleva la asociación desde su panel: en la web no puedes verlas ni cambiarlas, pero esta guía te explica qué contienen y cómo funcionan.

## Tu ficha

La ficha guarda tus datos como socio: nombre y apellidos, email, teléfono, dirección, fecha de nacimiento, fecha de alta y tipo de socio (regular, estudiante, senior, honorario o fundador). También tiene un **número de socio** con la forma «2026-0001»: el año del alta y un número de orden.

La ficha no es tu perfil. Cambiar tu nombre o tu email en tu perfil no cambia tu ficha, y al revés.

La ficha tiene uno de estos estados: **Activo**, **Inactivo**, **Suspendido** o **Expulsado**.

## Tu cuota

Cada cuota cubre un periodo, con una fecha de inicio y una fecha de fin. Según el tipo de periodo, la fecha de fin es:

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
- **Has pagado y no tienes el rol {{association_role}}**: tu nueva cuota puede seguir **Pendiente**. Avisa a la asociación para que la active.

[[action:about|Contactar con la asociación]]
