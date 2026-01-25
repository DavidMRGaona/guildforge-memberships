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
        'line2' => 'We recommend renewing it to continue enjoying all the benefits.',
        'action' => 'Renew membership',
        'salutation' => 'Thank you for being part of our association.',
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
        'line2' => 'To continue enjoying member benefits, we invite you to renew.',
        'action' => 'Renew membership',
        'salutation' => 'We hope to see you soon.',
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
        'action' => 'Make payment',
        'salutation' => 'Thank you for your cooperation.',
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
