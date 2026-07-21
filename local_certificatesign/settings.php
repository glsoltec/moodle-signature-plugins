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
        'local_certificatesign/signerreason',
        get_string('signerreason', 'local_certificatesign'),
        get_string('signerreason_help', 'local_certificatesign'),
        'Certificado de Curso',
        PARAM_TEXT
    ));

    $settings->add(new \admin_setting_configcheckbox(
        'local_certificatesign/autosign_enabled',
        get_string('autosign_enabled', 'local_certificatesign'),
        get_string('autosign_enabled_help', 'local_certificatesign'),
        1
    ));

    $settings->add(new \admin_setting_configselect(
        'local_certificatesign/task_interval',
        get_string('task_interval', 'local_certificatesign'),
        get_string('task_interval_help', 'local_certificatesign'),
        2,
        [1 => '1 ' . get_string('minutes'), 2 => '2 ' . get_string('minutes'),
         5 => '5 ' . get_string('minutes'), 10 => '10 ' . get_string('minutes'),
         15 => '15 ' . get_string('minutes'), 30 => '30 ' . get_string('minutes')]
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
