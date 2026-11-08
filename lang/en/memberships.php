<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation & UI Labels
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'members' => 'Members',
        'memberships' => 'Memberships',
        'fee_structures' => 'Fee structures',
        'settings' => 'Members',
    ],

    'model' => [
        'member' => [
            'singular' => 'member',
            'plural' => 'Members',
        ],
        'membership' => [
            'singular' => 'membership',
            'plural' => 'memberships',
        ],
        'fee' => [
            'singular' => 'payment',
            'plural' => 'payments',
        ],
        'fee_structure' => [
            'singular' => 'fee structure',
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
        'membership' => 'Membership',
        'membership_help' => 'The amount is filled in with the default fee for this member type and period, if there is one. You can change it.',
        'is_paid' => 'Already paid',
        'paid_on' => 'Payment date',
        'payment_status' => 'Payment status',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'valid_until_help' => 'Leave empty for indefinite validity',
        'is_default' => 'Default',
        'is_default_help' => 'The usual fee for this member type and period. There can only be one per member type and period type: marking this one unmarks the previous one.',
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
        'trashed' => 'Deleted',
    ],

    'actions' => [
        'view_deleted' => 'View record',
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
            'card' => 'Card',
            'other' => 'Other',
        ],
        'payment_status' => [
            'paid' => 'Paid',
            'pending' => 'Pending',
            'overdue' => 'Overdue',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'day_count' => '{1} :count day|[0,*] :count days',
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
            'permanent_members' => 'Permanent members',
            'notifications' => 'Notifications',
        ],
        'fields' => [
            'default_period_type' => 'Default period type',
            'default_period_type_help' => 'Defines how membership start and end dates are calculated',
            'academic_start_month' => 'Academic year start month',
            'academic_start_month_help' => 'Only applies if period type is academic year',
            'enable_role_assignment' => 'Assign role automatically',
            'enable_role_assignment_help' => 'Grants the association member role (chosen in Site settings → Authentication) to the linked user while their record is active and they have an active membership or a permanent type, and removes it when they no longer qualify',
            'permanent_member_types' => 'Permanent member types',
            'permanent_member_types_help' => 'While their record is active, members of these types hold the association member role without a membership and are left out of expiry warnings and overdue fees. After changing the list, run memberships:sync-roles to update the roles',
            'expiration_warning_days' => 'Warning days before expiration',
            'expiration_warning_days_help' => 'Days before expiration to send notification',
            'send_expired_email' => 'Notify expired memberships',
            'send_expired_email_help' => 'Emails the member when their membership expires, unless they already renewed. Only memberships that expire after turning it on.',
            'send_overdue_email' => 'Notify overdue fees',
            'send_overdue_email_help' => 'Sends a monthly reminder for every unpaid overdue fee, old ones included (fees already checked this month wait for the next one). Before turning it on, check that paid fees are marked as paid.',
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
    | Console Commands
    |--------------------------------------------------------------------------
    */
    'commands' => [
        'sync_roles' => [
            'disabled' => 'Association member role assignment is disabled in the module settings: nothing was changed.',
            'role_unavailable' => 'The association member role set in the core does not exist or cannot be used (Site settings → Authentication): nothing was changed.',
            'summary' => ':checked users checked, :added granted the role, :removed had the role removed',
            'dry_run' => 'Dry run: no role was changed.',
        ],
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
        'default_moved_title' => 'Default fee changed',
        'default_moved' => 'The :amount fee valid from :date is no longer the default for “:member_type” in “:period_type”.',
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
            'empty_state_description' => 'Create a membership to get started.',
        ],
        'fees' => [
            'title' => 'Payments',
            'create' => 'New payment',
            'edit' => 'Edit payment',
            'record_payment' => 'Record payment',
            'payment_recorded' => 'Payment recorded',
            'empty_state_description' => 'Add a payment to get started.',
            'delete_confirmation_check' => 'I understand that the payment will be deleted and that this cannot be undone.',
            'columns' => [
                'due_date' => 'Due',
                'payment_status' => 'Status',
                'paid_at' => 'Paid on',
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
        'fees' => [
            'view_any' => 'View member payments',
            'manage' => 'Manage member payments',
            'manage_help' => 'Create payments, record what is paid, edit and delete them in the Payments tab of the member.',
        ],
        'settings' => [
            'group' => 'Settings',
            'manage' => 'Manage the module settings',
            'manage_help' => 'Includes the automatic association member role and the permanent member types: it decides who gets the association member role.',
        ],
    ],
];
