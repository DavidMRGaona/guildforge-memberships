<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Membership expiring notification
    |--------------------------------------------------------------------------
    */
    'membership_expiring' => [
        'subject' => 'Your membership is about to expire',
        'greeting' => 'Hello :name,',
        'line1' => 'Your membership will expire in :days days (on :date).',
        'line2' => 'To renew it, get in touch with the association.',
        'action' => 'Contact the association',
        'salutation' => 'Thank you for being part of our association.',
        'site' => [
            'title' => 'Your membership expires soon',
            'body' => 'Expires in :days days (on :date). Get in touch with the association to renew it.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Membership expired notification
    |--------------------------------------------------------------------------
    */
    'membership_expired' => [
        'subject' => 'Your membership has expired',
        'greeting' => 'Hello :name,',
        'line1' => 'Your membership expired on :date.',
        'line2' => 'To renew it and keep your member benefits, get in touch with the association.',
        'action' => 'Contact the association',
        'salutation' => 'We hope to see you soon.',
        'site' => [
            'title' => 'Your membership has expired',
            'body' => 'Expired on :date. Get in touch with the association to renew it.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment overdue notification
    |--------------------------------------------------------------------------
    */
    'payment_overdue' => [
        'subject' => 'Payment overdue',
        'greeting' => 'Hello :name,',
        'line1' => 'You have a fee of :amount due on :date that has not been paid yet.',
        'line2' => 'The payment is :days days overdue.',
        'line3' => 'If you have already paid it, you can ignore this message; if in doubt, get in touch with the association.',
        'action' => 'Contact the association',
        'salutation' => 'Thank you for your cooperation.',
        'site' => [
            'title' => 'You have a fee pending payment',
            'body' => 'Fee of :amount due on :date, :days days overdue.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Command output
    |--------------------------------------------------------------------------
    */
    'command' => [
        'processing' => 'Processing expired memberships...',
        'expired_processed' => 'Memberships marked as expired',
        'expiring_notified' => 'Expiring notifications sent',
        'overdue_dispatched' => 'Overdue fee events dispatched',
    ],
];
