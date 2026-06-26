<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'certificatebeautifuldatainfo_usersignature';
$plugin->version      = 2025010100;
$plugin->requires     = 2025042800; // Moodle 5.0+
$plugin->dependencies = [
    'local_usersignature'      => 2025010100,
    'mod_certificatebeautiful' => ANY_VERSION,
];
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '2.0.0';
