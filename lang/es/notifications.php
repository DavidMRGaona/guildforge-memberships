<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Membership expiring notification
    |--------------------------------------------------------------------------
    */
    'membership_expiring' => [
        'subject' => 'Tu membresía está a punto de expirar',
        'greeting' => '¡Hola, :name!',
        'line1' => 'Tu membresía expirará en :days días (el :date).',
        'line2' => 'Para renovarla, ponte en contacto con la asociación.',
        'action' => 'Contactar con la asociación',
        'salutation' => 'Gracias por ser parte de nuestra asociación.',
        'site' => [
            'title' => 'Tu membresía expira pronto',
            'body' => 'Expira en :days días (el :date). Ponte en contacto con la asociación para renovarla.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Membership expired notification
    |--------------------------------------------------------------------------
    */
    'membership_expired' => [
        'subject' => 'Tu membresía ha expirado',
        'greeting' => '¡Hola, :name!',
        'line1' => 'Tu membresía ha expirado el :date.',
        'line2' => 'Para renovarla y seguir disfrutando de las ventajas de socio, ponte en contacto con la asociación.',
        'action' => 'Contactar con la asociación',
        'salutation' => 'Esperamos verte pronto.',
        'site' => [
            'title' => 'Tu membresía ha expirado',
            'body' => 'Expiró el :date. Ponte en contacto con la asociación para renovarla.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment overdue notification
    |--------------------------------------------------------------------------
    */
    'payment_overdue' => [
        'subject' => 'Cuota pendiente de pago',
        'greeting' => '¡Hola, :name!',
        'line1' => 'Tienes una cuota de :amount con fecha de vencimiento :date que aún no ha sido abonada.',
        'line2' => 'El pago tiene :days días de retraso.',
        'line3' => 'Si ya la has pagado, puedes ignorar este mensaje; si tienes dudas, ponte en contacto con la asociación.',
        'action' => 'Contactar con la asociación',
        'salutation' => 'Gracias por tu colaboración.',
        'site' => [
            'title' => 'Tienes una cuota pendiente de pago',
            'body' => 'Cuota de :amount con vencimiento el :date, con :days días de retraso.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Command output
    |--------------------------------------------------------------------------
    */
    'command' => [
        'processing' => 'Procesando membresías expiradas...',
        'expired_processed' => 'Membresías marcadas como expiradas',
        'expiring_notified' => 'Notificaciones de expiración enviadas',
        'overdue_dispatched' => 'Eventos de cuotas vencidas despachados',
    ],
];
