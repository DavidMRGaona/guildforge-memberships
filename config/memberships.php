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

    // Whether to grant the association member role (a core site setting, "socio" by default)
    // to the users linked to members that qualify, and revoke it from the others
    'enable_role_assignment' => false,

    /*
    |--------------------------------------------------------------------------
    | Permanent Member Types
    |--------------------------------------------------------------------------
    */

    // Member types that hold the role without a membership while their record is active,
    // and are left out of expiry warnings and overdue fees
    'permanent_member_types' => ['honorary'],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    // Days before expiration to send warning notification
    'expiration_warning_days' => 30,
];
