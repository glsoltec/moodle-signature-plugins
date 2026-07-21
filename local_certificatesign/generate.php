<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', \context_system::instance());

$PAGE->set_url('/local/certificatesign/generate.php');
$PAGE->set_context(\context_system::instance());
$PAGE->set_title(get_string('gen_title', 'local_certificatesign'));
$PAGE->set_heading(get_string('gen_title', 'local_certificatesign'));

$mform = new \local_certificatesign\form\generate_cert();

if ($mform->is_cancelled()) {
    redirect(new \moodle_url('/admin/settings.php', ['section' => 'local_certificatesign']));
}

if ($data = $mform->get_data()) {
    require_sesskey();

    try {
        $pfxcontent = \local_certificatesign\signer::generate_self_signed(
            $data->cn,
            $data->org ?? '',
            $data->country ?? '',
            $data->password
        );

        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $fs->delete_area_files($syscontext->id, 'local_certificatesign', 'pfxfile');
        $fs->create_file_from_string([
            'contextid' => $syscontext->id,
            'component' => 'local_certificatesign',
            'filearea'  => 'pfxfile',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'selfsigned_' . date('Ymd') . '.pfx',
        ], $pfxcontent);

        set_config('certpassword', $data->password, 'local_certificatesign');

        redirect(
            new \moodle_url('/admin/settings.php', ['section' => 'local_certificatesign']),
            get_string('gen_success', 'local_certificatesign'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\Exception $e) {
        redirect(
            $PAGE->url,
            get_string('erroropenssl', 'local_certificatesign', $e->getMessage()),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
