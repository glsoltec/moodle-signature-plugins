<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

class observer {

    private static $processing = false;

    public static function file_created(\core\event\file_created $event) {
        global $DB;

        if (self::$processing) {
            return;
        }

        $data = $event->get_data();

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($data['objectid']);
        if (!$file) {
            return;
        }

        if ($file->get_component() !== 'mod_certificatebeautiful' || $file->get_filearea() !== 'certificate') {
            return;
        }

        if (!get_config('local_certificatesign', 'autosign_enabled')) {
            return;
        }

        $pfxcontent = signer::get_pfx_content();
        $password = get_config('local_certificatesign', 'certpassword');
        if ($pfxcontent === null || empty($password)) {
            return;
        }

        self::$processing = true;

        try {
            $filename = $file->get_filename();
            $issuecode = basename($filename, '.pdf');
            $issue = $DB->get_record('certificatebeautiful_issue', ['code' => $issuecode]);
            if (!$issue) {
                self::$processing = false;
                return;
            }

            $dbman = $DB->get_manager();
            $logtable = new \xmldb_table('local_certificatesign_log');
            if (!$dbman->table_exists($logtable)) {
                self::$processing = false;
                return;
            }

            if ($DB->record_exists('local_certificatesign_log', ['issueid' => $issue->id])) {
                self::$processing = false;
                return;
            }

            $pdfcontent = $file->get_content();
            $signedpdf = signer::sign_pdf($pdfcontent);

            $filerecord = [
                'contextid' => $file->get_contextid(),
                'component' => $file->get_component(),
                'filearea'  => $file->get_filearea(),
                'itemid'    => $file->get_itemid(),
                'filepath'  => $file->get_filepath(),
                'filename'  => $filename,
            ];

            $file->delete();
            $fs->create_file_from_string($filerecord, $signedpdf);

            $DB->insert_record('local_certificatesign_log', (object)[
                'issueid'     => $issue->id,
                'timecreated' => time(),
            ]);
        } catch (\Exception $e) {
            debugging("local_certificatesign observer: {$e->getMessage()}", DEBUG_DEVELOPER);
        }

        self::$processing = false;
    }
}
