---
id: memberships.panel.member-role
title: Dar el rol de socio automáticamente
summary: Cómo da y quita el módulo el rol de socio según las fichas y las cuotas, cuándo hay que revisar los roles de todos y qué no hace.
category: memberships
audience: panel
order: 60
keywords: [rol de socio, rol, roles, asignar rol, quitar rol, retirar rol, automático, automáticamente, sincronizar, sync-roles, socio, socios, permanentes, honorario, acceso de socios, solo socios, no tiene el rol, pierde el rol]
related: [memberships.panel.settings, memberships.panel.manage-members, memberships.panel.memberships, core.panel.association-role, core.panel.roles-and-permissions]
conditions:
  permissions:
    all: ['memberships:settings.manage']
---
El rol {{association_role}} es el que distingue en la web a quien es de la asociación: lo usan las partes de la web reservadas a socios. El rol se elige en la configuración del sitio, en la pestaña **Autenticación**, campo **Rol de socio**. Este módulo puede darlo y quitarlo solo, según las fichas y las cuotas.

## Activarlo

1. En el menú lateral, abre **Gestión de socios** y elige **Configuración**.
2. En **Integración con roles**, activa **Asignar rol automáticamente**.
3. Revisa **Tipos de socio permanentes**: los tipos marcados no necesitan cuota.
4. Pulsa **Guardar**.

> [!IMPORTANT]
> Activarlo no revisa las fichas que ya existen: solo actúa cuando algo cambia. Para poner al día a todos los socios de una vez, pide a quien mantiene la instalación que ejecute el comando `memberships:sync-roles`. Con `--dry-run` solo cuenta lo que cambiaría, sin tocar nada.

## Quién tiene el rol

La cuenta vinculada a un socio tiene el rol {{association_role}} si alguna de sus fichas cumple a la vez:

- la ficha está en **Activo**, y
- el socio tiene una cuota **Activa** o es de un tipo permanente.

Una cuenta con varias fichas conserva el rol mientras una de ellas cumpla. Los socios sin **Usuario vinculado** no reciben ningún rol.

## Cuándo se revisa

El módulo vuelve a comprobarlo y da o quita el rol en cuanto cambia algo que lo afecta, desde el panel o cada noche:

- Una cuota se crea, cambia de estado (también al expirar sola), se borra o pasa a otro socio.
- La ficha cambia de estado o de tipo, se crea o se borra.
- La ficha cambia de **Usuario vinculado**: se revisan la cuenta anterior y la nueva.

El cambio de rol vale al momento.

## Lo que no hace

- Cambiar **Tipos de socio permanentes** no revisa los roles: después, pide que se ejecute `memberships:sync-roles`.
- Desactivar **Asignar rol automáticamente** deja de dar y quitar el rol, pero no retira los que ya dio. Si hace falta, quítalos en **Usuarios** o en **Roles**.
- Cambiar el rol de socio en la configuración del sitio no quita el anterior a quien lo tenía. Quítalo a mano y luego pide que se ejecute `memberships:sync-roles` para dar el nuevo.
- Nunca toca los roles de administración, edición ni el rol base de toda cuenta registrada.

> [!WARNING]
> Al quitar el rol, el módulo no distingue cómo se dio. Si das a mano el rol {{association_role}} a una cuenta vinculada a una ficha que no cumple, el siguiente cambio en esa ficha se lo quita.

## Si algo no sale bien

- **Nadie recibe el rol**: comprueba que **Asignar rol automáticamente** está activado y que el **Rol de socio** de la configuración del sitio existe. Si no se puede usar, el módulo no cambia ningún rol.
- **Una cuenta no recibe el rol**: comprueba que su ficha está en **Activo**, que está vinculada a esa cuenta y que tiene una cuota **Activa** (una **Pendiente** no cuenta).

[[action:filament.admin.pages.membership-settings|Abrir la configuración de socios]]
