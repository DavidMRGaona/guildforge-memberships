<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation & UI Labels
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'members' => 'Socios',
        'memberships' => 'Cuotas',
        'fee_structures' => 'Tarifas',
        'settings' => 'Socios',
    ],

    'model' => [
        'member' => [
            'singular' => 'socio',
            'plural' => 'Socios',
        ],
        'membership' => [
            'singular' => 'cuota',
            'plural' => 'cuotas',
        ],
        'fee' => [
            'singular' => 'pago',
            'plural' => 'pagos',
        ],
        'fee_structure' => [
            'singular' => 'tarifa',
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
        'membership' => 'Cuota',
        'membership_help' => 'El importe se rellena con la tarifa por defecto de este tipo de socio y periodo, si la hay. Puedes cambiarlo.',
        'is_paid' => 'Ya está pagado',
        'paid_on' => 'Fecha de pago',
        'payment_status' => 'Estado del pago',
        'valid_from' => 'Válido desde',
        'valid_until' => 'Válido hasta',
        'valid_until_help' => 'Dejar vacío para validez indefinida',
        'is_default' => 'Por defecto',
        'is_default_help' => 'La tarifa habitual de este tipo de socio y periodo. Solo puede haber una por tipo de socio y tipo de periodo: al marcar esta, la anterior deja de serlo.',
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
        'trashed' => 'Borrados',
    ],

    'actions' => [
        'view_deleted' => 'Ver ficha',
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
            'card' => 'Tarjeta',
            'other' => 'Otro',
        ],
        'payment_status' => [
            'paid' => 'Pagado',
            'pending' => 'Pendiente',
            'overdue' => 'Vencido',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'day_count' => '{1} :count día|[0,*] :count días',
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
        'default_moved_title' => 'Tarifa por defecto cambiada',
        'default_moved' => 'La tarifa de :amount válida desde el :date ha dejado de ser la tarifa por defecto de «:member_type» en «:period_type».',
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
            'create' => 'Nuevo pago',
            'edit' => 'Editar pago',
            'record_payment' => 'Registrar pago',
            'payment_recorded' => 'Pago registrado',
            'empty_state_description' => 'Añada un pago para empezar.',
            'delete_confirmation_check' => 'Entiendo que el pago se borrará y que no se puede deshacer.',
            'columns' => [
                'due_date' => 'Vencimiento',
                'payment_status' => 'Estado',
                'paid_at' => 'Pagado el',
            ],
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
            'import' => 'Importar socios',
            'import_help' => 'Dar de alta socios en bloque desde un fichero .csv o .xlsx, con su cuota actual, y vincularlos por email con sus cuentas.',
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
        'fees' => [
            'view_any' => 'Ver los pagos de los socios',
            'manage' => 'Gestionar los pagos de los socios',
            'manage_help' => 'Crear pagos, registrar cobros, editarlos y borrarlos en la pestaña Pagos de la ficha del socio.',
        ],
        'settings' => [
            'group' => 'Configuración',
            'manage' => 'Gestionar la configuración del módulo',
            'manage_help' => 'Incluye la asignación automática del rol de socio y los tipos de socio permanentes: decide quién recibe el rol de socio.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity (years as a member)
    |--------------------------------------------------------------------------
    */
    'activity' => [
        'year_label' => 'Años como socio',
        'year_title' => 'Socio en :year',
    ],

    /*
    |--------------------------------------------------------------------------
    | Member import
    |--------------------------------------------------------------------------
    */
    'import' => [
        'columns' => [
            'first_name' => 'Nombre',
            'last_name' => 'Apellidos',
            'email' => 'Email',
            'phone' => 'Teléfono',
            'birth_date' => 'Fecha de nacimiento',
            'address' => 'Dirección',
            'member_type' => 'Tipo de socio',
            'joined_at' => 'Fecha de alta',
            'member_number' => 'Número de socio',
            'notes' => 'Notas',
            'period_type' => 'Tipo de periodo',
            'period_start' => 'Inicio del periodo',
            'period_end' => 'Fin del periodo',
            'fee_amount' => 'Importe',
            'fee_due_date' => 'Vencimiento',
            'fee_paid_at' => 'Pagada el',
            'payment_method' => 'Forma de pago',
            'payment_reference' => 'Referencia',
        ],
        'last_separator' => ' y ',
        'action' => [
            'label' => 'Importar socios',
            'heading' => 'Importar socios',
            'description' => 'Da de alta socios desde un fichero .csv o .xlsx, con su cuota actual. Antes de importar verás cuántos se crearán y qué filas se saltarán, tienen errores o se vincularán a una cuenta.',
            'submit' => 'Importar',
        ],
        'steps' => [
            'file' => 'Fichero',
            'preview' => 'Vista previa',
        ],
        'fields' => [
            'file' => 'Fichero de socios',
            'file_help' => 'Un .csv o .xlsx de 2 MB como máximo y 1000 filas como mucho, con una fila de cabeceras. Solo Nombre y Apellidos son obligatorios.',
            'file_too_large' => 'El fichero no puede ocupar más de 2 MB.',
            'link_by_email' => 'Vincular por email con cuentas verificadas',
            'link_by_email_help' => 'Cada socio nuevo se vincula a la única cuenta activa y verificada con su email que no tenga ya una ficha. Si la cuenta recibe el rol de socio, se activan también sus roles que exigen ser socio; si alguno exige doble factor y la cuenta no lo tiene, esa persona recibe un aviso para activarlo.',
        ],
        'template' => [
            'label' => 'Descargar la plantilla',
            'file_name' => 'plantilla-socios.xlsx',
            'sheet_name' => 'Socios',
            'example_address' => 'Calle Mayor 1, 28001 Madrid',
            'example_reference' => 'TRF-0001',
        ],
        'preview' => [
            'no_file' => 'Sube un fichero para ver la vista previa.',
            'new' => '{1} Se creará :count socio|[2,*] Se crearán :count socios|[0] No se creará ningún socio',
            'duplicate' => '{1} :count ya existe y se saltará|[2,*] :count ya existen y se saltarán',
            'invalid' => '{1} :count fila tiene errores|[2,*] :count filas tienen errores',
            'linked' => '{1} :count se vinculará a una cuenta|[2,*] :count se vincularán a una cuenta',
            'ready' => 'Pulsa «Importar» para crearlos.',
            'blocked_by_errors' => 'No se importará nada mientras haya filas con errores. Corrígelas en el fichero y vuelve a subirlo.',
            'nothing_new' => 'No hay ningún socio nuevo que importar.',
            'too_many_rows' => 'El fichero tiene más de :max filas. Divídelo en varios.',
            'repeated_columns' => 'Hay dos cabeceras para la misma columna (:columns). Deja solo una y vuelve a subir el fichero.',
            'unknown_columns' => 'Estas columnas no se reconocen y no se importarán: :columns.',
            'table_heading' => 'Filas que no se crearán o que se vincularán a una cuenta',
            'no_name' => 'Sin nombre',
            'links_to' => 'Se vinculará a la cuenta de :account.',
            'columns' => [
                'line' => 'Fila',
                'member' => 'Socio',
                'outcome' => 'Qué pasará',
            ],
            'status' => [
                'new' => 'Se creará',
                'duplicate' => 'Ya existe',
                'invalid' => 'Con errores',
            ],
            'duplicate_reason' => [
                'member_number' => 'Ya hay un socio con este número de socio.',
                'email' => 'Ya hay un socio con este email.',
            ],
        ],
        'notifications' => [
            'imported_title' => '{1} Se ha importado :count socio|[2,*] Se han importado :count socios',
            'skipped' => '{1} :count fila se ha saltado porque el socio ya existía.|[2,*] :count filas se han saltado porque los socios ya existían.',
            'linked' => '{1} :count socio se ha vinculado a su cuenta.|[2,*] :count socios se han vinculado a su cuenta.',
            'not_imported_title' => 'No se ha importado ningún socio',
            'busy_title' => 'Hay otra importación en marcha',
            'busy_body' => 'No se ha importado nada. Vuelve a intentarlo en un momento.',
            'failed_title' => 'No se ha podido guardar la importación',
            'failed_body' => 'No se ha creado ningún socio. Vuelve a intentarlo y, si se repite, avisa a la administración del sitio.',
            'unreadable_title' => 'No se ha podido leer el fichero',
            'not_allowed_title' => 'No puedes importar socios',
            'not_allowed_body' => 'No se ha importado nada.',
            'template_failed_title' => 'No se ha podido preparar la plantilla',
            'template_failed_body' => 'Vuelve a intentarlo en un momento.',
        ],
        'busy' => 'Ya hay otra importación de socios en marcha. Inténtalo de nuevo en un momento.',
        'link_skipped' => [
            'would_remove_association_role' => 'No se vincula a la cuenta: perdería el rol de socio que ya tiene, porque esta ficha no tiene un periodo en curso.',
        ],
        'errors' => [
            'required' => 'Falta este dato.',
            'too_long' => 'Admite como mucho :max caracteres.',
            'email' => 'No es un email válido.',
            'date' => 'Escribe la fecha como 31/12/2025 o 2025-12-31.',
            'future_date' => 'No puede ser una fecha futura.',
            'member_type' => 'No es un tipo de socio. Usa uno de estos: :options.',
            'period_type' => 'No es un tipo de periodo. Usa uno de estos: :options.',
            'payment_method' => 'No es una forma de pago. Usa una de estas: :options.',
            'member_number' => 'Escribe el número como AAAA-NNNN, por ejemplo 2025-0001.',
            'member_numbers_exhausted' => 'No quedan números de socio libres en :year.',
            'repeated_in_file' => 'Se repite en las filas :lines.',
            'amount' => 'Escribe el importe como 25, 25,00 o 25.00, sin signo negativo.',
            'fee_needs_period' => 'La cuota necesita un periodo: indica su inicio, su fin o su tipo.',
            'period_type_required' => 'Indica el tipo de periodo: las fechas no encajan con ninguno.',
            'period_end_before_start' => 'El fin del periodo no puede ser anterior a su inicio.',
            'period_end_required' => 'Indica el fin del periodo: no se puede deducir de su inicio.',
            'paid_at_required' => 'Indica la fecha de pago para guardar la forma de pago o la referencia.',
        ],
    ],
];
