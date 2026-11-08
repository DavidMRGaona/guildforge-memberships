---
id: memberships.panel.payments
title: Los pagos de los socios
summary: Anota lo que tiene que pagar cada socio por sus cuotas, registra los cobros y sigue los pagos pendientes y vencidos.
category: memberships
audience: panel
order: 35
keywords: [pago, pagos, cobro, cobrar, cobrado, ha pagado, registrar pago, nuevo pago, importe, vencimiento, fecha de vencimiento, pendiente, vencido, impago, método de pago, efectivo, transferencia, tarjeta, referencia, recibo]
related: [memberships.panel.memberships, memberships.panel.fee-structures, memberships.panel.expiry-and-notices]
conditions:
  permissions:
    all: ['memberships:fees.view_any']
---
Un pago es lo que un socio tiene que abonar por una de sus cuotas: un importe con su fecha de vencimiento. Cada socio tiene los suyos en la pestaña **Pagos** de su ficha, con los de todas sus cuotas.

Para ver la pestaña necesitas el permiso **Ver los pagos de los socios**. Para crear, cobrar, editar o borrar pagos, **Gestionar los pagos de los socios**.

## El estado de cada pago

| Estado | Qué significa |
|---|---|
| **Pagado** | Tiene fecha de pago. |
| **Pendiente** | Sin pagar, y su fecha de vencimiento no ha pasado. |
| **Vencido** | Sin pagar desde el día siguiente a su fecha de vencimiento. Sale en **Cuotas vencidas sin pagar** del escritorio y, si está activado **Avisar de las cuotas atrasadas**, el socio recibe un recordatorio al mes. |

## Añadir un pago

1. En el menú lateral, abre **Gestión de socios** y elige **Socios**.
2. Pulsa **Editar** en la fila del socio y abre la pestaña **Pagos**.
3. Pulsa **Nuevo pago**.
4. Elige la **Cuota**. Si hay una tarifa **Por defecto** para el tipo de socio y el tipo de periodo de esa cuota, el **Importe** y la **Moneda** se rellenan con ella. Puedes cambiarlos, por ejemplo si cobras solo una parte del año.
5. Elige la **Fecha de vencimiento**. Empieza en hoy.
6. Si ya lo ha pagado, marca **Ya está pagado** y rellena la **Fecha de pago**, el **Método de pago** y, si quieres, la **Referencia de transacción**.
7. Si quieres, escribe unas **Notas**.
8. Pulsa **Crear**.

## Registrar un cobro

Cuando el socio pague un pago **Pendiente** o **Vencido**:

1. En la pestaña **Pagos**, pulsa **Registrar pago** en su fila.
2. Elige la **Fecha de pago** (empieza en hoy) y el **Método de pago**. Si quieres, escribe la **Referencia de transacción**.
3. Pulsa **Enviar**.

El pago pasa a **Pagado** y deja de contar en **Pagos pendientes**.

## Corregir o borrar un pago

- **Corregir**: abre el menú **⋯** de su fila, pulsa **Editar**, cambia lo que necesites y pulsa **Guardar cambios**. Si quitas **Ya está pagado**, el pago vuelve a estar pendiente.
- **Borrar**: abre el menú **⋯** de su fila, pulsa **Borrar**, marca la casilla que confirma que no se puede deshacer y confirma. El pago desaparece del historial del socio.

La tabla oculta el **Método de pago** y la **Referencia de transacción**: muéstralos con el botón de columnas, encima de la tabla.

Registrar un cobro no activa la cuota: si la cuota está **Pendiente**, actívala en la pestaña **Cuotas**.

## Si algo no sale bien

- **El importe no se rellena solo**: no hay ninguna tarifa **Por defecto** para el tipo de socio y el tipo de periodo de esa cuota. Escríbelo a mano o crea la tarifa.
- **No ves la pestaña Pagos**: te falta el permiso **Ver los pagos de los socios**.
- **No ves Nuevo pago ni Registrar pago**: te falta el permiso **Gestionar los pagos de los socios**.

[[action:filament.admin.resources.members.index|Ver los socios|if=memberships:members.view_any]]
