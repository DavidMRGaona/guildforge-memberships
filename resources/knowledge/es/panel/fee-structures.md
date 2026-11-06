---
id: memberships.panel.fee-structures
title: Tarifas por tipo de socio
summary: Anota cuánto cuesta la cuota de cada tipo de socio y periodo, y desde cuándo vale cada importe.
category: memberships
audience: panel
order: 40
keywords: [tarifa, tarifas, precio, precios, importe, cuánto cuesta, cuota anual, coste, euros, moneda, por defecto, validez, válido desde, válido hasta, subir la cuota, cambiar el precio, estudiante, senior]
related: [memberships.panel.overview, memberships.panel.memberships]
conditions:
  permissions:
    all: ['memberships:fee_structures.view_any']
---
Las tarifas recogen el importe de la cuota para cada tipo de socio y cada tipo de periodo: por ejemplo, la cuota de **Estudiante** en **Año natural**. Sirven de referencia para quien lleva las cuotas.

> [!IMPORTANT]
> Por ahora, el panel no aplica las tarifas a las cuotas: una cuota guarda su periodo y su estado, pero no su importe.

## Ver las tarifas

En el menú lateral, abre **Gestión de socios** y elige **Tarifas**. La lista muestra el tipo de socio, el tipo de periodo, el importe, la validez y si es la tarifa **Por defecto**. Una tarifa sin fecha de fin sale como **Indefinida**. Puedes filtrar por **Tipo de socio** y **Tipo de periodo**.

## Crear una tarifa

Necesitas el permiso **Crear tarifa**.

1. En la lista de tarifas, pulsa **Crear tarifa**.
2. En **Datos de la tarifa**, elige el **Tipo de socio** y el **Tipo de periodo**, y escribe el **Importe**, con dos decimales como mucho.
3. Deja **Moneda** en «EUR» o escribe otro código de tres letras (por ejemplo, «USD»).
4. En **Validez**, elige **Válido desde** (empieza en hoy). Deja **Válido hasta** vacío si la tarifa no tiene fecha de fin.
5. Marca **Por defecto** si es la tarifa habitual de ese tipo de socio.
6. Si quieres, escribe una **Descripción**.
7. Pulsa **Crear**.

## Cambiar el precio

Para que se vea qué importe valía en cada momento, no cambies el importe de una tarifa antigua: pon su **Válido hasta** en el último día del precio viejo y crea otra con el precio nuevo desde el día siguiente.

Para editar una tarifa, pulsa **Editar** en su fila, cambia lo que necesites y pulsa **Guardar cambios** (permiso **Editar tarifa**). Para borrarla, pulsa **Borrar** y confirma (permiso **Eliminar tarifa**). Borrar una tarifa no cambia ninguna cuota.

## Si algo no sale bien

- **El importe no se acepta**: usa punto para los decimales y como mucho dos, por ejemplo «25.50».
- **«Válido hasta» no se acepta**: tiene que ser posterior a **Válido desde**.

[[action:filament.admin.resources.fee-structures.index|Ver las tarifas]]
[[action:filament.admin.resources.fee-structures.create|Crear una tarifa|if=memberships:fee_structures.create]]
