<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/filelib.php');

global $DB, $USER, $CFG;

$code   = required_param('code', PARAM_TEXT);
$action = optional_param('action', 'view', PARAM_TEXT);
$token  = optional_param('token', '', PARAM_TEXT);

// ─── Autenticação ─────────────────────────────────────────────────────────────
if ($token) {
    $externalservice = $DB->get_record("external_services", ["shortname" => MOODLE_OFFICIAL_MOBILE_SERVICE]);
    if ($externalservice) {
        $externaltoken = $DB->get_record("external_tokens", [
            "token" => $token, "externalserviceid" => $externalservice->id
        ], "userid");
        if ($externaltoken) {
            $tokenuser = $DB->get_record("user", ["id" => $externaltoken->userid]);
            if ($tokenuser) {
                \core\session\manager::login_user($tokenuser);
            }
        }
    }
}

// ─── Dados do certificado ─────────────────────────────────────────────────────
$issue = $DB->get_record('certificatebeautiful_issue', ['code' => $code], '*', MUST_EXIST);
$cm    = get_coursemodule_from_id('certificatebeautiful', $issue->cmid, 0, false, MUST_EXIST);
$course = $DB->get_record("course", ["id" => $cm->course], '*', MUST_EXIST);
$context = \context_module::instance($cm->id);

if (!$token) {
    require_course_login($course, true, $cm);
}
require_capability('mod/certificatebeautiful:view', $context);

/** @var \mod_certificatebeautiful\vo\certificatebeautiful $certificate */
$certificate = $DB->get_record('certificatebeautiful', ['id' => $cm->instance], '*', MUST_EXIST);
$user = $DB->get_record("user", ["id" => $issue->userid]);

$fs = get_file_storage();
$filerecord = [
    'contextid' => $context->id,
    'component' => 'mod_certificatebeautiful',
    'filearea'  => 'certificate',
    'itemid'    => $user->id,
    'filepath'  => '/',
    'filename'  => "{$issue->code}.pdf",
];

$username = fullname($user);
$filename = "{$certificate->name} - {$username}.pdf";

// ─── Verificar se já foi assinado ─────────────────────────────────────────────
$logentry = $DB->get_record('local_certificatesign_log', ['issueid' => $issue->id]);

// ─── Carregar ou gerar o PDF ─────────────────────────────────────────────────
$storedfile = $fs->get_file(
    $filerecord['contextid'], $filerecord['component'],
    $filerecord['filearea'], $filerecord['itemid'],
    $filerecord['filepath'], $filerecord['filename']
);

if ($storedfile && $logentry) {
    $pdfcontent = $storedfile->get_content();
} else if ($storedfile) {
    $pdfcontent = $storedfile->get_content();
    try {
        $signedpdf = \local_certificatesign\signer::sign_pdf($pdfcontent);
        $storedfile->delete();
        $fs->create_file_from_string($filerecord, $signedpdf);
        $DB->insert_record('local_certificatesign_log', (object)[
            'issueid'     => $issue->id,
            'timecreated' => time(),
        ]);
        $pdfcontent = $signedpdf;
    } catch (\Exception $e) {
        debugging("local_certificatesign serve: {$e->getMessage()}", DEBUG_DEVELOPER);
    }
} else {
    try {
        $model = $DB->get_record('certificatebeautiful_model',
            ['id' => $certificate->model], '*', MUST_EXIST);
        $model->pages_info_object = json_decode($model->pages_info);

        /** @var \mod_certificatebeautiful\pdf\page_pdf $pagepdf */
        $pagepdf = new \mod_certificatebeautiful\pdf\page_pdf();
        $pdfcontent = $pagepdf->create_pdf($certificate, $issue, $model, $user, $course);

        $fs->create_file_from_string($filerecord, $pdfcontent);

        try {
            $signedpdf = \local_certificatesign\signer::sign_pdf($pdfcontent);
            $fs->get_file(
                $filerecord['contextid'], $filerecord['component'],
                $filerecord['filearea'], $filerecord['itemid'],
                $filerecord['filepath'], $filerecord['filename']
            )->delete();
            $fs->create_file_from_string($filerecord, $signedpdf);
            $DB->insert_record('local_certificatesign_log', (object)[
                'issueid'     => $issue->id,
                'timecreated' => time(),
            ]);
            $pdfcontent = $signedpdf;
        } catch (\Exception $e) {
            debugging("local_certificatesign serve sign: {$e->getMessage()}", DEBUG_DEVELOPER);
        }
    } catch (\Exception $e) {
        $viewurl = new \moodle_url('/mod/certificatebeautiful/view-pdf.php',
            ['code' => $code, 'action' => $action]);
        redirect($viewurl);
    }
}

// ─── Servir PDF assinado ─────────────────────────────────────────────────────
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($action === 'download' ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfcontent));
header('Cache-Control: public, must-revalidate, max-age=0');
header('Pragma: public');
echo $pdfcontent;
die();
