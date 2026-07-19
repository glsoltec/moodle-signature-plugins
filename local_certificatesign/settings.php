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

    $settings->add(new \local_certificatesign\admin_setting_certinfo());

    $settings->add(new admin_setting_configtext(
        'local_certificatesign/signername',
        get_string('signername', 'local_certificatesign'),
        get_string('signername_help', 'local_certificatesign'),
        '',
        PARAM_TEXT,
        50
    ));

    $settings->add(new admin_setting_configtext(
        'local_certificatesign/signerlocation',
        get_string('signerlocation', 'local_certificatesign'),
        get_string('signerlocation_help', 'local_certificatesign'),
        '',
        PARAM_TEXT,
        50
    ));

    $settings->add(new admin_setting_configtext(
        'local_certificatesign/signerreason',
        get_string('signerreason', 'local_certificatesign'),
        get_string('signerreason_help', 'local_certificatesign'),
        'Certificado de Curso',
        PARAM_TEXT
    ));

    $genurl = new \moodle_url('/local/certificatesign/generate.php');
    $settings->add(new admin_setting_heading(
        'local_certificatesign/generatecert',
        get_string('gen_heading', 'local_certificatesign'),
        get_string('gen_heading_desc', 'local_certificatesign') . '<br>' .
        \html_writer::link($genurl, get_string('gen_btn', 'local_certificatesign'),
            ['class' => 'btn btn-primary mt-2'])
    ));
}
