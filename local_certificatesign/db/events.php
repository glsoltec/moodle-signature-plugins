<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\file_created',
        'callback'  => '\local_certificatesign\observer::file_created',
        'priority'  => 200,
    ],
];
