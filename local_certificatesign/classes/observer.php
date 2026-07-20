<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

    private static $processing = false;

    public static function file_created(\core\event\file_created $event) {
        global $DB;

        if (self::$processing) {
            return;
        }

        $data = $event->get_data();
        $other = $data['other'];

        if ($other['component'] !== 'mod_certificatebeautiful' || $other['filearea'] !== 'certificate') {
            return;
        }

        if (!get_config('local_certificatesign', 'autosign_enabled') || !self::is_configured()) {
            return;
        }

        self::$processing = true;

        try {
            $fs = get_file_storage();
            $file = $fs->get_file($data['contextid'], $other['component'], $other['filearea'],
                $other['itemid'], $other['filepath'], $other['filename']);
            if (!$file) {
                self::$processing = false;
                return;
            }

            $filename = $other['filename'];
            $issuecode = basename($filename, '.pdf');
            $issue = $DB->get_record('certificatebeautiful_issue', ['code' => $issuecode]);
            if (!$issue) {
                self::$processing = false;
                return;
            }

            $existing = $DB->record_exists('local_certificatesign_log', ['issueid' => $issue->id]);
            if ($existing) {
                self::$processing = false;
                return;
            }

            $pdfcontent = $file->get_content();
            $signedpdf = signer::sign_pdf($pdfcontent);

            $file->delete();
            $fs->create_file_from_string([
                'contextid' => $data['contextid'],
                'component' => $other['component'],
                'filearea'  => $other['filearea'],
                'itemid'    => $other['itemid'],
                'filepath'  => $other['filepath'],
                'filename'  => $filename,
            ], $signedpdf);

            self::log_signed($issue->id);
        } catch (\Exception $e) {
            debugging("local_certificatesign observer: {$e->getMessage()}", DEBUG_DEVELOPER);
        }

        self::$processing = false;
    }

    private static function is_configured(): bool {
        $pfxcontent = signer::get_pfx_content();
        $password = get_config('local_certificatesign', 'certpassword');
        return $pfxcontent !== null && !empty($password);
    }

    private static function log_signed(int $issueid): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_certificatesign_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('issueid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('issueid', XMLDB_KEY_UNIQUE, ['issueid']);
            $dbman->create_table($table);
        }

        $existing = $DB->get_record('local_certificatesign_log', ['issueid' => $issueid]);
        if (!$existing) {
            $DB->insert_record('local_certificatesign_log', (object)[
                'issueid'     => $issueid,
                'timecreated' => time(),
            ]);
        }
    }
}
