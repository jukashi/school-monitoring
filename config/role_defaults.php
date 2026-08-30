<?php

return [
    'Super Administrator' => [
        'description' => 'Full system access; its permissions cannot be reduced.',
        'permissions' => ['*'],
    ],
    'Administrator' => [
        'description' => 'Manages school operations, profiles, academics, users, and reports.',
        'permissions' => [
            'dashboard.view', 'students.view', 'students.create', 'students.edit', 'students.export', 'students.import',
            'enrollments.view', 'enrollments.manage', 'student_accounts.reset_password',
            'teachers.view', 'teachers.create', 'teachers.edit', 'teachers.export',
            'academics.view', 'academics.manage', 'attendance.view', 'attendance.record', 'attendance.edit', 'attendance.export',
            'events.view', 'events.create', 'events.edit', 'events.manage_participants', 'reports.view', 'reports.export',
            'tuition.view', 'tuition.manage', 'tuition.record_payment', 'tuition.export',
            'insurance.view', 'insurance.manage', 'insurance.claims', 'insurance.export',
            'inventory.view', 'inventory.manage', 'inventory.issue', 'inventory.export',
            'users.view', 'users.create', 'users.edit', 'audit.view', 'settings.view',
        ],
    ],
    'Registrar' => [
        'description' => 'Manages student records and enrollment without finance or system-administration access.',
        'permissions' => [
            'dashboard.view',
            'students.view', 'students.create', 'students.edit', 'students.export', 'students.import',
            'enrollments.view', 'enrollments.manage', 'student_accounts.reset_password',
            'teachers.view', 'academics.view',
            'attendance.view', 'attendance.export',
            'events.view', 'reports.view', 'reports.export',
        ],
    ],
    'Teacher' => [
        'description' => 'Views advisory students and records permitted attendance.',
        'permissions' => ['dashboard.view', 'students.view', 'teachers.view', 'academics.view', 'attendance.view', 'attendance.record', 'events.view', 'reports.view'],
    ],
    'Student' => [
        'description' => 'Views and edits only the linked personal profile and views published events.',
        'permissions' => ['dashboard.view', 'students.view', 'attendance.view', 'events.view'],
    ],
    'Viewer / Staff' => [
        'description' => 'Read-only access to general monitoring information.',
        'permissions' => ['dashboard.view', 'teachers.view', 'academics.view', 'attendance.view', 'events.view', 'reports.view'],
    ],
];
