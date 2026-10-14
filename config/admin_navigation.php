<?php

$staff = ['super_admin', 'admin', 'assistant'];
$admins = ['super_admin', 'admin'];
$owner = ['super_admin'];

return [
    'groups' => [
        'daily' => [
            'label' => 'Today & Staff', 'roles' => $staff,
            'items' => [
                'admin.dashboard' => ['label' => 'Dashboard', 'roles' => $admins],
                'admin.operations.index' => ['label' => 'Today & Operations', 'active' => ['admin.operations.*'], 'shortcut' => 'Today'],
                'admin.notifications.index' => ['label' => 'Notifications', 'roles' => $admins, 'active' => ['admin.notifications.*'], 'badge' => true],
                'admin.tasks.index' => ['label' => 'Staff Tasks', 'active' => ['admin.tasks.*']],
                'admin.staff-bins.index' => ['label' => 'Staff Notes', 'active' => ['admin.staff-bins.*']],
                'admin.staff-note-templates.library' => ['label' => 'Templates & Snippets', 'active' => ['admin.staff-note-templates.*']],
                'admin.reports.activity' => ['label' => 'Daily Business & Staff Activity', 'roles' => $admins],
            ],
        ],
        'students' => [
            'label' => 'Students', 'roles' => $staff,
            'items' => [
                'admin.students.index' => ['label' => 'Student Records', 'active' => ['admin.students.*'], 'shortcut' => 'Students'],
                'admin.contacts.index' => ['label' => 'Students & Contacts', 'active' => ['admin.contacts.*']],
                'admin.leads' => ['label' => 'Leads (Resource Inquiries)'],
                'admin.data-quality.index' => ['label' => 'Data Quality Center', 'roles' => $admins, 'active' => ['admin.data-quality.*']],
            ],
        ],
        'scheduling' => [
            'label' => 'Scheduling', 'roles' => $admins,
            'items' => [
                'admin.bookings.index' => ['label' => 'Bookings & Calendar', 'roles' => $staff, 'active' => ['admin.bookings.*'], 'shortcut' => 'Bookings'],
                'admin.availability.index' => ['label' => 'Tutor Availability', 'active' => ['admin.availability.*']],
                'admin.recurring.index' => ['label' => 'Recurring Lessons', 'active' => ['admin.recurring.*']],
                'admin.waitlist.index' => ['label' => 'Lesson Waitlist', 'active' => ['admin.waitlist.*']],
                'admin.booking-policy.index' => ['label' => 'Booking Policy', 'active' => ['admin.booking-policy.*']],
                'admin.meeting-links.index' => ['label' => 'Meeting Links', 'active' => ['admin.meeting-links.*']],
            ],
        ],
        'finance' => [
            'label' => 'Finance', 'roles' => $admins,
            'items' => [
                'admin.billing.cashier' => ['label' => 'Cashier Hub', 'active' => ['admin.billing.cashier.*'], 'shortcut' => 'Cashier'],
                'admin.billing.reconcile' => ['label' => 'Billing Reconcile', 'active' => ['admin.billing.reconcile*']],
                'admin.receivables.index' => ['label' => 'Receivables & Statements', 'active' => ['admin.receivables.*']],
                'admin.payment-methods.index' => ['label' => 'Payment Methods', 'active' => ['admin.payment-methods.*']],
            ],
        ],
        'learning' => [
            'label' => 'Learning', 'roles' => $admins,
            'items' => [
                'admin.lms.courses.index' => ['label' => 'Courses', 'active' => ['admin.lms.courses.*']],
                'admin.lms.operations' => ['label' => 'Learning Operations', 'active' => ['admin.lms.*']],
                'admin.resources.index' => ['label' => 'Resources', 'active' => ['admin.resources.*']],
                'admin.resource-collections.index' => ['label' => 'Resource Collections', 'active' => ['admin.resource-collections.*']],
                'admin.games.index' => ['label' => 'Learning Games', 'active' => ['admin.games.*']],
                'admin.forms.index' => ['label' => 'Student Forms', 'roles' => $staff, 'active' => ['admin.forms.*']],
                'admin.forms.completion' => ['label' => 'Form Completion', 'roles' => $staff],
            ],
        ],
        'website' => [
            'label' => 'Website Content', 'roles' => $admins,
            'items' => [
                'admin.content.index' => ['label' => 'Content & FAQs', 'active' => ['admin.content.*']],
                'admin.pages.index' => ['label' => 'Pages CMS', 'active' => ['admin.pages.*']],
                'admin.blog.index' => ['label' => 'Blogs', 'active' => ['admin.blog.*']],
                'admin.media.index' => ['label' => 'Media Library', 'active' => ['admin.media.*']],
                'admin.promotions.index' => ['label' => 'Promotions Portal', 'active' => ['admin.promotions.*']],
            ],
        ],
        'reports' => [
            'label' => 'Reports & Analytics', 'roles' => $admins,
            'items' => [
                'admin.reports.lifecycle' => ['label' => 'Business Lifecycle'],
                'admin.reports.index' => ['label' => 'Operational Reports & Exports', 'active' => ['admin.reports.*']],
                'admin.analytics' => ['label' => 'Analytics & Funnels'],
                'admin.analytics.countries' => ['label' => 'Country Analytics', 'active' => ['admin.analytics.countries*']],
                'admin.analytics.sections' => ['label' => 'Section Attention', 'active' => ['admin.analytics.sections*']],
            ],
        ],
        'system' => [
            'label' => 'Settings & System', 'roles' => $admins,
            'items' => [
                'admin.settings.index' => ['label' => 'Settings & Policies', 'active' => ['admin.settings.*']],
                'admin.telegram.index' => ['label' => 'Telegram Bots', 'active' => ['admin.telegram.*']],
                'admin.reason-codes.index' => ['label' => 'Reason Categories', 'roles' => $owner, 'active' => ['admin.reason-codes.*']],
                'admin.security.show' => ['label' => 'Account Security', 'roles' => $owner, 'active' => ['admin.security.*']],
                'admin.health' => ['label' => 'System Health', 'roles' => $owner],
                'admin.backups.index' => ['label' => 'Backups & Recovery', 'roles' => $owner, 'active' => ['admin.backups.*']],
                'admin.administrators.index' => ['label' => 'Staff Administrators', 'roles' => $owner, 'active' => ['admin.administrators.*']],
                'admin.audit-logs' => ['label' => 'Security Audit Log', 'roles' => $owner],
                'admin.analytics.maintenance' => ['label' => 'Maintenance Mode', 'roles' => $owner, 'active' => ['admin.analytics.maintenance*']],
                'admin.development-tools.index' => ['label' => 'Development & Launch Tools', 'roles' => $owner, 'active' => ['admin.development-tools.*'], 'enabled' => 'development_tools.enabled', 'danger' => true],
            ],
        ],
    ],
];
