<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation & UI Labels
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Gestión de socios',

    'navigation' => [
        'members' => 'Socios',
        'memberships' => 'Cuotas',
        'fee_structures' => 'Tarifas',
        'settings' => 'Configuración',
    ],

    'model' => [
        'member' => [
            'singular' => 'Socio',
            'plural' => 'Socios',
        ],
        'membership' => [
            'singular' => 'Cuota',
            'plural' => 'Cuotas',
        ],
        'fee_structure' => [
            'singular' => 'Tarifa',
            'plural' => 'Tarifas',
        ],
    ],

    'pages' => [
        'create_member' => 'Crear socio',
        'edit_member' => 'Editar socio',
        'create_membership' => 'Crear cuota',
        'edit_membership' => 'Editar cuota',
        'create_fee_structure' => 'Crear tarifa',
        'edit_fee_structure' => 'Editar tarifa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */
    'fields' => [
        'member_number' => 'Número de socio',
        'first_name' => 'Nombre',
        'last_name' => 'Apellidos',
        'full_name' => 'Nombre completo',
        'email' => 'Email',
        'phone' => 'Teléfono',
        'birth_date' => 'Fecha de nacimiento',
        'address' => 'Dirección',
        'member_type' => 'Tipo de socio',
        'status' => 'Estado',
        'user_id' => 'Usuario vinculado',
        'user_id_help' => 'Usuario del sistema vinculado a este socio',
        'notes' => 'Notas',
        'joined_at' => 'Fecha de alta',
        'created_at' => 'Creado',
        'start_date' => 'Fecha de inicio',
        'end_date' => 'Fecha de fin',
        'period_type' => 'Tipo de periodo',
        'amount' => 'Importe',
        'currency' => 'Moneda',
        'currency_help' => 'Código ISO 4217 (ej: EUR, USD)',
        'due_date' => 'Fecha de vencimiento',
        'paid_at' => 'Fecha de pago',
        'payment_method' => 'Método de pago',
        'transaction_reference' => 'Referencia de transacción',
        'valid_from' => 'Válido desde',
        'valid_until' => 'Válido hasta',
        'valid_until_help' => 'Dejar vacío para validez indefinida',
        'is_default' => 'Por defecto',
        'is_default_help' => 'Usar esta tarifa como predeterminada para este tipo de socio',
        'description' => 'Descripción',
        'activated_at' => 'Fecha de activación',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections
    |--------------------------------------------------------------------------
    */
    'sections' => [
        'personal_data' => 'Datos personales',
        'contact_data' => 'Datos de contacto',
        'status' => 'Estado y vinculación',
        'fee_structure_data' => 'Datos de la tarifa',
        'validity' => 'Validez',
        'additional_info' => 'Información adicional',
        'memberships' => 'Cuotas',
    ],

    'tabs' => [
        'member_data' => 'Datos del socio',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'member_type' => 'Tipo de socio',
        'status' => 'Estado',
        'period_type' => 'Tipo de periodo',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Labels
    |--------------------------------------------------------------------------
    */
    'status' => [
        'indefinite' => 'Indefinida',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enums
    |--------------------------------------------------------------------------
    */
    'enums' => [
        'member_type' => [
            'regular' => 'Regular',
            'student' => 'Estudiante',
            'senior' => 'Senior',
            'honorary' => 'Honorario',
            'founder' => 'Fundador',
        ],
        'member_status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'suspended' => 'Suspendido',
            'expelled' => 'Expulsado',
        ],
        'membership_status' => [
            'pending' => 'Pendiente',
            'active' => 'Activa',
            'expired' => 'Expirada',
            'cancelled' => 'Cancelada',
        ],
        'membership_period_type' => [
            'calendar_year' => 'Año natural',
            'academic_year' => 'Año académico',
            'rolling' => 'Rotativo',
        ],
        'payment_method' => [
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia bancaria',
            'credit_card' => 'Tarjeta de crédito',
            'other' => 'Otro',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'stats' => [
            'total_members' => 'Total de socios',
            'total_members_description' => 'Todos los socios registrados',
            'active_members' => 'Socios activos',
            'active_members_description' => 'Socios con estado activo',
            'members_with_membership' => 'Con cuota activa',
            'members_with_membership_description' => 'Socios con cuota vigente',
            'pending_payments' => 'Pagos pendientes',
            'pending_payments_description' => 'Cuotas no pagadas',
        ],
        'expiring_memberships' => [
            'title' => 'Cuotas próximas a expirar',
            'member' => 'Socio',
            'end_date' => 'Fecha de fin',
            'days_remaining' => 'Días restantes',
            'view_member' => 'Ver socio',
        ],
        'overdue_fees' => [
            'title' => 'Cuotas vencidas sin pagar',
            'member' => 'Socio',
            'amount' => 'Importe',
            'due_date' => 'Vencimiento',
            'days_overdue' => 'Días de retraso',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'title' => 'Configuración del módulo',
        'sections' => [
            'membership_period' => 'Configuración de periodo de cuota',
            'role_integration' => 'Integración con roles',
            'permanent_members' => 'Socios permanentes',
            'notifications' => 'Notificaciones',
        ],
        'fields' => [
            'default_period_type' => 'Tipo de periodo predeterminado',
            'default_period_type_help' => 'Define cómo se calculan las fechas de inicio y fin de las cuotas',
            'academic_start_month' => 'Mes de inicio del año académico',
            'academic_start_month_help' => 'Solo aplica si el tipo de periodo es año académico',
            'enable_role_assignment' => 'Asignar rol automáticamente',
            'enable_role_assignment_help' => 'Asigna el rol de socio (se elige en Configuración del sitio → Autenticación) al usuario vinculado mientras su ficha está activa y tiene una cuota activa o es de un tipo permanente, y se lo retira cuando deja de cumplirlo',
            'permanent_member_types' => 'Tipos de socio permanentes',
            'permanent_member_types_help' => 'Con la ficha activa, los socios de estos tipos tienen el rol de socio sin necesitar una cuota y no aparecen en los avisos de caducidad ni en las cuotas vencidas. Tras cambiar la lista, ejecuta memberships:sync-roles para actualizar los roles',
            'expiration_warning_days' => 'Días de aviso antes de expiración',
            'expiration_warning_days_help' => 'Días antes de la expiración para enviar notificación',
            'send_expired_email' => 'Avisar de la membresía expirada',
            'send_expired_email_help' => 'Envía un correo al socio cuando su membresía expira, salvo que ya la haya renovado. Solo afecta a las que expiren después de activarlo.',
            'send_overdue_email' => 'Avisar de las cuotas atrasadas',
            'send_overdue_email_help' => 'Envía un recordatorio al mes por cada cuota vencida sin pagar, también las antiguas (las ya revisadas este mes esperan al siguiente). Antes de activarlo, comprueba que las cuotas pagadas estén marcadas como pagadas.',
        ],
        'months' => [
            '1' => 'Enero',
            '2' => 'Febrero',
            '3' => 'Marzo',
            '4' => 'Abril',
            '5' => 'Mayo',
            '6' => 'Junio',
            '7' => 'Julio',
            '8' => 'Agosto',
            '9' => 'Septiembre',
            '10' => 'Octubre',
            '11' => 'Noviembre',
            '12' => 'Diciembre',
        ],
        'saved' => 'Configuración guardada correctamente',
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Commands
    |--------------------------------------------------------------------------
    */
    'commands' => [
        'sync_roles' => [
            'disabled' => 'La asignación del rol de socio está desactivada en la configuración del módulo: no se ha cambiado nada.',
            'role_unavailable' => 'El rol de socio configurado en el core no existe o no se puede usar (Configuración del sitio → Autenticación): no se ha cambiado nada.',
            'summary' => ':checked usuarios revisados, :added con rol añadido, :removed con rol retirado',
            'dry_run' => 'Simulación: no se ha cambiado ningún rol.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Structure Fields
    |--------------------------------------------------------------------------
    */
    'fee_structure' => [
        'fields' => [
            'member_type' => 'Tipo de socio',
            'period_type' => 'Tipo de periodo',
            'amount' => 'Importe',
            'currency' => 'Moneda',
            'valid_from' => 'Válido desde',
            'valid_until' => 'Válido hasta',
            'description' => 'Descripción',
            'is_default' => 'Por defecto',
            'proration_rules' => 'Reglas de prorrateo',
        ],
        'sections' => [
            'fee_data' => 'Datos de la tarifa',
            'validity' => 'Validez',
            'additional' => 'Información adicional',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Relation Managers
    |--------------------------------------------------------------------------
    */
    'relation_managers' => [
        'memberships' => [
            'title' => 'Cuotas',
            'activated_at' => 'Activada',
            'empty_state_description' => 'Cree una cuota para empezar.',
        ],
        'fees' => [
            'title' => 'Pagos',
            'is_paid' => 'Pagado',
            'record_payment' => 'Registrar pago',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'members' => [
            'view_any' => 'Ver listado de socios',
            'view' => 'Ver socio',
            'create' => 'Crear socio',
            'update' => 'Editar socio',
            'delete' => 'Eliminar socio',
        ],
        'memberships' => [
            'view_any' => 'Ver listado de cuotas',
            'view' => 'Ver cuota',
            'create' => 'Crear cuota',
            'update' => 'Editar cuota',
            'delete' => 'Eliminar cuota',
        ],
        'fee_structures' => [
            'view_any' => 'Ver listado de tarifas',
            'view' => 'Ver tarifa',
            'create' => 'Crear tarifa',
            'update' => 'Editar tarifa',
            'delete' => 'Eliminar tarifa',
        ],
    ],
];
