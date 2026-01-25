<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation & UI Labels
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Member management',

    'navigation' => [
        'members' => 'Members',
        'memberships' => 'Memberships',
        'fee_structures' => 'Fee structures',
        'settings' => 'Settings',
    ],

    'model' => [
        'member' => [
            'singular' => 'Member',
            'plural' => 'Members',
        ],
        'membership' => [
            'singular' => 'Membership',
            'plural' => 'Memberships',
        ],
        'fee_structure' => [
            'singular' => 'Fee structure',
            'plural' => 'Fee structures',
        ],
    ],

    'pages' => [
        'create_member' => 'Create member',
        'edit_member' => 'Edit member',
        'create_membership' => 'Create membership',
        'edit_membership' => 'Edit membership',
        'create_fee_structure' => 'Create fee structure',
        'edit_fee_structure' => 'Edit fee structure',
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */
    'fields' => [
        'member_number' => 'Member number',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'full_name' => 'Full name',
        'email' => 'Email',
        'phone' => 'Phone',
        'birth_date' => 'Birth date',
        'address' => 'Address',
        'member_type' => 'Member type',
        'status' => 'Status',
        'user_id' => 'Linked user',
        'user_id_help' => 'System user linked to this member',
        'notes' => 'Notes',
        'joined_at' => 'Joined date',
        'created_at' => 'Created',
        'start_date' => 'Start date',
        'end_date' => 'End date',
        'period_type' => 'Period type',
        'amount' => 'Amount',
        'currency' => 'Currency',
        'currency_help' => 'ISO 4217 code (e.g., EUR, USD)',
        'due_date' => 'Due date',
        'paid_at' => 'Paid at',
        'payment_method' => 'Payment method',
        'transaction_reference' => 'Transaction reference',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'valid_until_help' => 'Leave empty for indefinite validity',
        'is_default' => 'Default',
        'is_default_help' => 'Use this fee as default for this member type',
        'description' => 'Description',
        'activated_at' => 'Activated at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections
    |--------------------------------------------------------------------------
    */
    'sections' => [
        'personal_data' => 'Personal data',
        'contact_data' => 'Contact data',
        'status' => 'Status and linking',
        'fee_structure_data' => 'Fee structure data',
        'validity' => 'Validity',
        'additional_info' => 'Additional information',
        'memberships' => 'Memberships',
    ],

    'tabs' => [
        'member_data' => 'Member data',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'member_type' => 'Member type',
        'status' => 'Status',
        'period_type' => 'Period type',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Labels
    |--------------------------------------------------------------------------
    */
    'status' => [
        'indefinite' => 'Indefinite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enums
    |--------------------------------------------------------------------------
    */
    'enums' => [
        'member_type' => [
            'regular' => 'Regular',
            'student' => 'Student',
            'senior' => 'Senior',
            'honorary' => 'Honorary',
            'founder' => 'Founder',
        ],
        'member_status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended',
            'expelled' => 'Expelled',
        ],
        'membership_status' => [
            'pending' => 'Pending',
            'active' => 'Active',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
        ],
        'membership_period_type' => [
            'calendar_year' => 'Calendar year',
            'academic_year' => 'Academic year',
            'rolling' => 'Rolling',
        ],
        'payment_method' => [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank transfer',
            'credit_card' => 'Credit card',
            'other' => 'Other',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'stats' => [
            'total_members' => 'Total members',
            'total_members_description' => 'All registered members',
            'active_members' => 'Active members',
            'active_members_description' => 'Members with active status',
            'members_with_membership' => 'With active membership',
            'members_with_membership_description' => 'Members with current membership',
            'pending_payments' => 'Pending payments',
            'pending_payments_description' => 'Unpaid fees',
        ],
        'expiring_memberships' => [
            'title' => 'Memberships expiring soon',
            'member' => 'Member',
            'end_date' => 'End date',
            'days_remaining' => 'Days remaining',
            'view_member' => 'View member',
        ],
        'overdue_fees' => [
            'title' => 'Overdue unpaid fees',
            'member' => 'Member',
            'amount' => 'Amount',
            'due_date' => 'Due date',
            'days_overdue' => 'Days overdue',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'title' => 'Module settings',
        'sections' => [
            'membership_period' => 'Membership period settings',
            'role_integration' => 'Role integration',
            'grace_and_notifications' => 'Grace period and notifications',
            'fees' => 'Fee settings',
        ],
        'fields' => [
            'default_period_type' => 'Default period type',
            'default_period_type_help' => 'Defines how membership start and end dates are calculated',
            'academic_start_month' => 'Academic year start month',
            'academic_start_month_help' => 'Only applies if period type is academic year',
            'enable_role_assignment' => 'Assign role automatically',
            'enable_role_assignment_help' => 'Assign a role when membership is active',
            'member_role_name' => 'Member role name',
            'member_role_name_help' => 'The role to be assigned to active members',
            'grace_period_days' => 'Grace period days',
            'grace_period_days_help' => 'Days after expiration before deactivating membership',
            'expiration_warning_days' => 'Warning days before expiration',
            'expiration_warning_days_help' => 'Days before expiration to send notification',
            'enable_proration' => 'Enable proration',
            'enable_proration_help' => 'Calculate fees proportionally for mid-period registrations',
            'default_currency' => 'Default currency',
            'default_currency_help' => 'Currency for fees (ISO 4217 code)',
        ],
        'months' => [
            '1' => 'January',
            '2' => 'February',
            '3' => 'March',
            '4' => 'April',
            '5' => 'May',
            '6' => 'June',
            '7' => 'July',
            '8' => 'August',
            '9' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December',
        ],
        'saved' => 'Settings saved successfully',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Structure Fields
    |--------------------------------------------------------------------------
    */
    'fee_structure' => [
        'fields' => [
            'member_type' => 'Member type',
            'period_type' => 'Period type',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'valid_from' => 'Valid from',
            'valid_until' => 'Valid until',
            'description' => 'Description',
            'is_default' => 'Default',
            'proration_rules' => 'Proration rules',
        ],
        'sections' => [
            'fee_data' => 'Fee data',
            'validity' => 'Validity',
            'additional' => 'Additional information',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Relation Managers
    |--------------------------------------------------------------------------
    */
    'relation_managers' => [
        'memberships' => [
            'title' => 'Memberships',
            'activated_at' => 'Activated',
        ],
        'fees' => [
            'title' => 'Payments',
            'is_paid' => 'Paid',
            'record_payment' => 'Record payment',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'members' => [
            'view_any' => 'View members list',
            'view' => 'View member',
            'create' => 'Create member',
            'update' => 'Edit member',
            'delete' => 'Delete member',
        ],
        'memberships' => [
            'view_any' => 'View memberships list',
            'view' => 'View membership',
            'create' => 'Create membership',
            'update' => 'Edit membership',
            'delete' => 'Delete membership',
        ],
        'fee_structures' => [
            'view_any' => 'View fee structures list',
            'view' => 'View fee structure',
            'create' => 'Create fee structure',
            'update' => 'Edit fee structure',
            'delete' => 'Delete fee structure',
        ],
    ],
];
