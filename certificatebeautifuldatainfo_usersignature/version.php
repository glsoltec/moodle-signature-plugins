<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'certificatebeautifuldatainfo_usersignature';
$plugin->version      = 2026062701;
$plugin->requires     = 2025041400; // Moodle 5.0.0 (Build: 20250414)+
$plugin->dependencies = [
    'local_usersignature'      => 2026062701,
    'mod_certificatebeautiful' => ANY_VERSION,
];
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '2.0.2';
