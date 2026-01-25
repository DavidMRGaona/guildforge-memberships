<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Membership Period Configuration
    |--------------------------------------------------------------------------
    */

    // Default period type: 'calendar_year', 'academic_year', 'rolling'
    'default_period_type' => 'calendar_year',

    // Month when academic year starts (1 = January, 9 = September)
    'academic_start_month' => 9,

    /*
    |--------------------------------------------------------------------------
    | Role Integration
    |--------------------------------------------------------------------------
    */

    // Whether to assign a role when membership is activated
    'enable_role_assignment' => false,

    // Name of the role to assign to active members
    'member_role_name' => 'member',

    /*
    |--------------------------------------------------------------------------
    | Grace Period and Notifications
    |--------------------------------------------------------------------------
    */

    // Days of grace period after membership expiration
    'grace_period_days' => 30,

    // Days before expiration to send warning notification
    'expiration_warning_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Fee Configuration
    |--------------------------------------------------------------------------
    */

    // Whether to enable prorated fees for mid-period registrations
    'enable_proration' => true,

    // Default currency for fees
    'default_currency' => 'EUR',
];
