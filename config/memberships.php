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

    // Whether to grant the role when a membership is activated (and revoke it on expiry)
    'enable_role_assignment' => false,

    // Name of the role to assign to active members
    'member_role_name' => 'member',

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    // Days before expiration to send warning notification
    'expiration_warning_days' => 30,
];
