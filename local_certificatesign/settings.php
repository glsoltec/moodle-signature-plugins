<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_certificatesign', get_string('settings', 'local_certificatesign'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configstoredfile(
        'local_certificatesign/pfxfile',
        get_string('pfxfile', 'local_certificatesign'),
        get_string('pfxfile_help', 'local_certificatesign'),
        'pfxfile',
        0,
        ['accepted_types' => ['.pfx', '.p12']]
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_certificatesign/certpassword',
        get_string('certpassword', 'local_certificatesign'),
        get_string('certpassword_help', 'local_certificatesign'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_certificatesign/signername',
        get_string('signername', 'local_certificatesign'),
        get_string('signername_help', 'local_certificatesign'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_certificatesign/signerlocation',
        get_string('signerlocation', 'local_certificatesign'),
        get_string('signerlocation_help', 'local_certificatesign'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_certificatesign/signerreason',
        get_string('signerreason', 'local_certificatesign'),
        get_string('signerreason_help', 'local_certificatesign'),
        'Certificado de Curso',
        PARAM_TEXT
    ));
}
