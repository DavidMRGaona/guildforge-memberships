---
id: memberships.panel.manage-members
title: Dar de alta y editar socios
summary: Crea la ficha de un socio, vincúlala a su cuenta de la web, cambia su estado o bórrala.
category: memberships
audience: panel
order: 20
keywords: [alta, dar de alta, nuevo socio, crear socio, añadir socio, inscribir socio, ficha, editar socio, datos del socio, vincular, usuario vinculado, cuenta, baja, dar de baja, suspender, expulsar, borrar socio, eliminar socio, número de socio, buscar socio]
related: [memberships.panel.memberships, memberships.panel.overview, memberships.panel.member-role, core.panel.manage-users]
conditions:
  permissions:
    any: ['memberships:members.create', 'memberships:members.update']
---
Cada persona asociada tiene una ficha de socio. Aquí se crea, se mantiene al día y se borra. Sus cuotas tienen su propia guía.

## Dar de alta un socio

1. En el menú lateral, abre **Personas** y elige **Socios**.
2. Pulsa **Crear socio**.
3. En **Datos personales**, rellena **Nombre** y **Apellidos**, que son obligatorios, y si quieres la **Fecha de nacimiento**. Elige el **Tipo de socio** (empieza en **Regular**).
4. En **Datos de contacto**, rellena **Email**, **Teléfono** y **Dirección** si los tienes.
5. En **Estado y vinculación**, revisa el **Estado** (empieza en **Activo**) y la **Fecha de alta** (empieza en este momento).
6. Si la persona tiene cuenta en la web, búscala por su nombre en **Usuario vinculado**.
7. Pulsa **Crear**.

El socio recibe solo su **Número de socio**: el año del alta y el siguiente número libre de ese año, por ejemplo «2026-0001». No se puede cambiar. Al guardar vuelves a la lista de socios.

> [!TIP]
> Un socio nuevo todavía no tiene cuota. Ábrelo y crea la primera en la pestaña **Cuotas**.

## Vincular la ficha a una cuenta

**Usuario vinculado** une la ficha con una cuenta de la web. Sirve para dos cosas:

- Los correos del módulo sobre sus cuotas van al email de esa cuenta. Sin cuenta vinculada, van al **Email** de la ficha, si lo tiene.
- Si está activada la asignación automática, esa cuenta recibe el rol {{association_role}} mientras la ficha lo cumpla. Un socio sin cuenta vinculada no recibe ningún rol.

Al cambiar o quitar la cuenta vinculada, se revisa el rol de la cuenta anterior y el de la nueva.

## Editar un socio

1. En la lista de socios, abre el menú **⋯** de su fila y pulsa **Editar**. Puedes buscarlo por número, nombre o email, y filtrar por **Tipo de socio** o **Estado**.
2. En la pestaña **Datos del socio**, cambia lo que necesites.
3. Pulsa **Guardar cambios**.

Cambiar el **Estado** o el **Tipo de socio** también revisa el rol de socio: **Suspendido**, **Expulsado** e **Inactivo** lo retiran aunque haya una cuota activa, y volver a **Activo** lo devuelve si se cumple lo demás.

## Borrar un socio

Pulsa **Borrar** en el menú **⋯** de su fila o dentro de la ficha, y confirma. Desde la lista también puedes borrar varios a la vez.

> [!TIP]
> Borrar un socio no pierde su historial: sus cuotas y pagos se conservan y vuelven con él si lo restauras. Si solo deja la asociación, también puedes poner su ficha en **Inactivo** en lugar de borrarla.

Al borrarlo, si estaba vinculado a una cuenta, se le retira el rol de socio salvo que otra ficha suya lo siga cumpliendo.

## Restaurar un socio borrado

Hace falta el mismo permiso que para borrar.

1. En la lista de socios, abre los filtros y en **Borrados** elige **Solo registros eliminados** para ver únicamente los borrados, o **Con registros eliminados** para verlos junto al resto.
2. Abre el menú **⋯** de su fila y pulsa **Restaurar**. También puedes marcar varios y restaurarlos a la vez.

El socio vuelve a la lista con sus datos, cuotas y pagos tal como estaban, y recupera el rol de socio si su ficha lo cumple. **Ver ficha** abre un socio borrado solo para consultarlo, con sus cuotas y pagos; hasta que lo restaures no se puede editar ni registrar pagos.

## Si algo no sale bien

- **No encuentras la cuenta en Usuario vinculado**: busca por el nombre de la cuenta, no por el email. La persona tiene que haberse registrado antes.
- **La cuenta no recibe el rol {{association_role}}**: comprueba que la ficha está en **Activo**, que tiene una cuota **Activa** (o es de un tipo permanente) y que la asignación automática está activada en la configuración del módulo.

[[action:filament.admin.resources.members.create|Dar de alta un socio|if=memberships:members.create]]
[[action:filament.admin.resources.members.index|Ver los socios|if=memberships:members.view_any]]
