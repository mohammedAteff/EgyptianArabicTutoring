<?php

return [
    'groups' => [
        'home' => ['label' => 'Home', 'items' => [
            'student.home' => ['label' => 'Home', 'mobile' => 'Portal home', 'icon' => 'home', 'active' => ['student.forms.*']],
        ]],
        'learning' => [
            'label' => 'Learning',
            'items' => [
                'student.dashboard' => ['label' => 'Sessions', 'mobile' => 'Portal sessions', 'icon' => 'sessions', 'active' => ['student.bookings.*', 'student.lessons.*']],
                'student.learning.index' => ['label' => 'My Learning', 'mobile' => 'Learn', 'icon' => 'learning', 'active' => ['student.learning.*', 'student.evidence.*']],
                'student.insights.index' => ['label' => 'insights.title', 'icon' => 'insights', 'active' => ['student.insights.*'], 'mobile_primary' => false, 'width' => 'wide'],
                'student.teaching.index' => ['label' => 'Practice & Feedback', 'mobile' => 'Practice', 'icon' => 'practice', 'active' => ['student.teaching.*', 'student.progress-reports.*']],
            ],
        ],
        'personal' => [
            'label' => 'Personal tools',
            'items' => [
                'student.scheduling.index' => ['label' => 'Planning', 'icon' => 'planning', 'active' => ['student.scheduling.*'], 'width' => 'wide'],
                'student.bins.index' => ['label' => 'Notes', 'icon' => 'notes', 'active' => ['student.bins.*'], 'width' => 'focused'],
                'student.bookmarks.index' => ['label' => 'Bookmarks', 'icon' => 'bookmarks', 'active' => ['student.bookmarks.*']],
                'student.feedback.index' => ['label' => 'Feedback', 'icon' => 'practice', 'active' => ['student.feedback.*']],
            ],
        ],
        'records' => [
            'label' => 'Records',
            'items' => [
                'student.statements.index' => ['label' => 'Statements & Receipts', 'icon' => 'records', 'active' => ['student.statements.*', 'student.receipts.*'], 'width' => 'wide'],
            ],
        ],
        'account' => [
            'label' => 'Your Account',
            'items' => [
                'student.profile' => ['label' => 'Profile', 'icon' => 'profile', 'active' => ['student.profile.*'], 'width' => 'focused'],
                'student.security.show' => ['label' => 'Account Security', 'icon' => 'security', 'active' => ['student.security.*'], 'width' => 'focused'],
                'student.video.devices' => ['label' => 'Authorized Browsers', 'icon' => 'browsers', 'active' => ['student.video.devices.*']],
            ],
        ],
        'utility' => [
            'label' => 'Notifications',
            'items' => [
                'student.notifications.index' => ['label' => 'Notifications', 'icon' => 'notifications', 'active' => ['student.notifications.*']],
            ],
        ],
    ],
];
