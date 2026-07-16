<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_usersignature', get_string('pluginname', 'local_usersignature'));
    $ADMIN->add('localplugins', $settings);
}
