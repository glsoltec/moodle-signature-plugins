<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/usersignature:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_USER,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
