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
        'greeting' => 'Hola :name,',
        'line1' => 'Tu membresía expirará en :days días (el :date).',
        'line2' => 'Te recomendamos renovarla para seguir disfrutando de todos los beneficios.',
        'action' => 'Renovar membresía',
        'salutation' => 'Gracias por ser parte de nuestra asociación.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Membership expired notification
    |--------------------------------------------------------------------------
    */
    'membership_expired' => [
        'subject' => 'Tu membresía ha expirado',
        'greeting' => 'Hola :name,',
        'line1' => 'Tu membresía ha expirado el :date.',
        'line2' => 'Para seguir disfrutando de los beneficios de socio, te invitamos a renovarla.',
        'action' => 'Renovar membresía',
        'salutation' => 'Esperamos verte pronto.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment overdue notification
    |--------------------------------------------------------------------------
    */
    'payment_overdue' => [
        'subject' => 'Cuota pendiente de pago',
        'greeting' => 'Hola :name,',
        'line1' => 'Tienes una cuota de :amount con fecha de vencimiento :date que aún no ha sido abonada.',
        'line2' => 'El pago tiene :days días de retraso.',
        'action' => 'Realizar pago',
        'salutation' => 'Gracias por tu colaboración.',
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
