---
id: memberships.panel.manage-members
title: Dar de alta y editar socios
summary: Crea la ficha de un socio o impórtalos desde una hoja de cálculo, vincúlala a su cuenta de la web, cambia su estado o bórrala.
category: memberships
audience: panel
order: 20
keywords: [importar, importar socios, carga masiva, csv, xlsx, excel, hoja de cálculo, plantilla, alta, dar de alta, nuevo socio, crear socio, añadir socio, inscribir socio, ficha, editar socio, datos del socio, vincular, usuario vinculado, cuenta, baja, dar de baja, suspender, expulsar, borrar socio, eliminar socio, número de socio, buscar socio]
related: [memberships.panel.memberships, memberships.panel.overview, memberships.panel.member-role, core.panel.manage-users]
conditions:
  permissions:
    any: ['memberships:members.create', 'memberships:members.update', 'memberships:members.import']
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

## Importar socios

Si ya tienes a los socios en una hoja de cálculo, puedes darlos de alta de una vez, cada uno con su cuota actual. Hacen falta los permisos **Importar socios** y **Ver listado de socios**, porque la importación empieza en la lista.

1. En la lista de socios, pulsa **Importar socios**.
2. Sube un fichero `.csv` o `.xlsx` de 2 MB como máximo y 1000 filas como mucho. Si no tienes uno, pulsa **Descargar la plantilla**: trae todas las columnas y una fila de ejemplo. Bórrala antes de importar, porque si no se importaría como un socio más.
3. Marca **Vincular por email con cuentas verificadas** solo si quieres unir cada ficha a la cuenta de la web con su email. Empieza desmarcada.
4. Pulsa **Siguiente** para ver la vista previa: cuántos socios se crearán, cuántos ya existen y cuántas filas tienen errores, con una tabla de las filas que no se crearán y de las que se vincularán a una cuenta.
5. Pulsa **Importar**. Al terminar verás cuántos socios se han creado.

**Importar** solo se puede pulsar si no hay ninguna fila con errores y hay al menos un socio nuevo. Si hay errores no se crea nadie: corrige el fichero y vuelve a subirlo. Al importar se lee de nuevo el fichero y se comprueba todo otra vez, así que lo que haya cambiado mientras tanto también cuenta. El fichero subido se borra al importar, y también si al pulsar **Importar** el fichero no se acepta. Si cierras el diálogo sin importar, el sitio lo borra más tarde.

### Formato

- La primera fila son las cabeceras, con los nombres de la plantilla. Da igual que estén en mayúsculas o sin tildes, y el orden de las columnas tampoco importa.
- Solo **Nombre** y **Apellidos** son obligatorios. Las columnas que no se reconocen se ignoran y la vista previa las nombra.
- Cada fila es un socio con, si se indica, su periodo actual y la cuota de ese periodo. Las cuotas anteriores no se importan: añádelas desde la ficha del socio si las necesitas.

### Valores admitidos

- **Fechas**: `31/12/2025` o `2025-12-31`. El alta y el nacimiento no pueden ser futuros.
- **Importe**: `25`, `25,00` o `25.00`, sin signo negativo.
- **Tipo de socio**, **Tipo de periodo** y **Forma de pago**: el nombre que ves en el panel, por ejemplo «Regular», «Año natural» o «Transferencia bancaria».
- **Número de socio**: con el formato `AAAA-NNNN`. Si lo dejas vacío, se genera con el año de la fecha de alta, como al crear un socio a mano.
- **Periodo**: si das el inicio y el fin sin tipo, se elige el tipo con el que encajan. Si falta el inicio o el fin, se deduce del tipo.
- **Cuota**: necesita un periodo. Vence el primer día del periodo si no indicas otra fecha. La forma de pago y la referencia necesitan la fecha de pago.

### Socios que ya existen

Una fila cuyo **Número de socio** o **Email** ya tiene otro socio, también uno borrado, se salta: nunca se cambia un socio que ya existe. Por eso puedes subir dos veces el mismo fichero sin duplicar a nadie. Si dos filas del mismo fichero repiten número o email, las dos se marcan con error.

### Vincular con las cuentas

Con la casilla marcada, cada socio nuevo se une a la única cuenta activa con su email, siempre que la cuenta lo tenga verificado y no esté vinculada ya a otra ficha. Si hay dudas, el socio entra sin vincular y puedes unirlo después en **Usuario vinculado**.

Una cuenta que ya tiene el rol {{association_role}} no se vincula a una ficha que se lo quitaría (sin un periodo en curso ni un tipo permanente); la vista previa lo explica en su fila.

> [!WARNING]
> Vincular una cuenta puede darle el rol {{association_role}} y, con él, activar sus roles que exigen ser socio. Si alguno de esos roles exige doble factor y la cuenta no lo tiene activado, la persona recibe un aviso para activarlo. Es lo esperado, no un fallo.

La importación no envía correos de bienvenida ni de cuotas: solo el aviso de doble factor descrito arriba. Las cuotas que ya estén vencidas se detectan después, en la revisión diaria de siempre.

## Si algo no sale bien

- **No encuentras la cuenta en Usuario vinculado**: busca por el nombre de la cuenta, no por el email. La persona tiene que haberse registrado antes.
- **Importar no se puede pulsar**: la vista previa dice por qué. Corrige en el fichero las filas con errores, deja una sola cabecera por columna o divide un fichero de más de 1000 filas, y vuelve a subirlo.
- **«Hay otra importación en marcha»**: alguien está importando a la vez. Espera un momento y vuelve a subir el fichero.
- **La cuenta no recibe el rol {{association_role}}**: comprueba que la ficha está en **Activo**, que tiene una cuota **Activa** (o es de un tipo permanente) y que la asignación automática está activada en la configuración del módulo.

[[action:filament.admin.resources.members.create|Dar de alta un socio|if=memberships:members.create]]
[[action:filament.admin.resources.members.index|Ver los socios|if=memberships:members.view_any]]
