---
id: memberships.panel.memberships
title: Crear y renovar las cuotas de un socio
summary: Crea la cuota de un socio, actívala cuando corresponda, renuévala con la del periodo siguiente o cancélala.
category: memberships
audience: panel
order: 30
keywords: [cuota, cuotas, crear cuota, nueva cuota, renovar, renovación, activar cuota, pendiente, activa, expirada, cancelada, cancelar cuota, periodo, tipo de periodo, año natural, año académico, curso, rotativo, fecha de inicio, fecha de fin, membresía, pago, ha pagado, cuta]
related: [memberships.panel.manage-members, memberships.panel.expiry-and-notices, memberships.panel.fee-structures, memberships.panel.settings]
conditions:
  permissions:
    all: ['memberships:members.update']
---
Una cuota es un periodo de pertenencia de un socio. Cada socio tiene las suyas en la pestaña **Cuotas** de su ficha. Renovar es crear la cuota del periodo siguiente.

## Crear una cuota

1. En el menú lateral, abre **Gestión de socios** y elige **Socios**.
2. Pulsa **Editar** en la fila del socio y abre la pestaña **Cuotas**.
3. Pulsa **Crear cuota**.
4. Elige el **Tipo de periodo**. Empieza en el que diga la configuración del módulo.
5. Elige la **Fecha de inicio**. La **Fecha de fin** se rellena sola según el tipo de periodo. Puedes cambiarla después, siempre que sea posterior a la de inicio.
6. Elige el **Estado**. Empieza en **Pendiente**.
7. Si quieres, escribe unas **Notas**.
8. Pulsa **Crear**.

## Cómo se calcula la fecha de fin

| Tipo de periodo | Fecha de fin |
|---|---|
| **Año natural** | El 31 de diciembre del año de inicio. |
| **Año académico** | El último día del mes anterior al **Mes de inicio del año académico** de la configuración (septiembre por defecto, así que el 31 de agosto). Si la cuota empieza en ese mes o después, acaba al año siguiente. |
| **Rotativo** | Un año después de la fecha de inicio, menos un día. |

La cuota vale hasta el final de su último día.

## Activar una cuota

Una cuota **Pendiente** no cuenta: no da el rol de socio ni sale en los avisos de caducidad. Cuando corresponda, por ejemplo al recibir el pago:

1. En la pestaña **Cuotas**, pulsa **Editar** en su fila.
2. Cambia el **Estado** a **Activa**.
3. Pulsa **Guardar cambios**.

La columna **Activada** guarda cuándo se activó. Si está activada la asignación automática, la cuenta vinculada recibe el rol {{association_role}} en ese momento.

## Renovar

Crea la cuota del periodo siguiente, con la **Fecha de inicio** del día después del fin de la actual, y actívala cuando toque. No hace falta tocar la anterior: cada noche, las cuotas activas cuya fecha de fin ya ha pasado pasan solas a **Expirada**.

> [!TIP]
> Si creas la cuota siguiente antes de que expire la actual, aunque sea **Pendiente**, el socio no recibe el correo de cuota expirada.

## Cancelar o borrar una cuota

- **Cancelar**: edita la cuota y cambia el **Estado** a **Cancelada**. La cuota queda en el historial del socio.
- **Borrar**: pulsa **Borrar** en su fila y confirma. La cuota desaparece. No se puede deshacer.

En los dos casos, si era la cuota activa, se revisa el rol de socio de la cuenta vinculada. Poner una cuota en **Expirada** a mano tiene el mismo efecto que su caducidad: puede enviar el correo de cuota expirada.

## Las marcas de las notas

Cuando el módulo avisa a un socio de que su cuota está a punto de expirar, lo anota en las **Notas** de la cuota con una marca como `[EXPIRING_NOTIFIED:2026]`. No la borres: si la quitas, el aviso puede volver a enviarse.

## Si algo no sale bien

- **La fecha de fin no se rellena**: elige primero el **Tipo de periodo** y la **Fecha de inicio**; se calcula al cambiar cualquiera de los dos.
- **El socio no recibe el rol {{association_role}}**: la cuota tiene que estar **Activa** y la ficha en **Activo**, con una cuenta en **Usuario vinculado**.
