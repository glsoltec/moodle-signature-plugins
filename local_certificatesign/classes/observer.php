<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function file_created(\core\event\file_created $event) {
        global $DB;

        $data = $event->get_data();
        if ($data['component'] !== 'mod_certificatebeautiful' || $data['filearea'] !== 'certificate') {
            return;
        }

        if (!self::is_configured()) {
            return;
        }

        try {
            $fs = get_file_storage();
            $file = $fs->get_file_by_id($data['objectid']);
            if (!$file) {
                return;
            }

            $pdfcontent = $file->get_content();
            $signedpdf = signer::sign_pdf($pdfcontent);

            $file->delete();
            $fs->create_file_from_string([
                'contextid' => $data['contextid'],
                'component' => $data['component'],
                'filearea'  => $data['filearea'],
                'itemid'    => $data['itemid'],
                'filepath'  => $data['filepath'],
                'filename'  => $data['filename'],
            ], $signedpdf);

            $issue = $DB->get_record('certificatebeautiful_issue', ['code' => basename($data['filename'], '.pdf')]);
            if ($issue) {
                self::log_signed($issue->id);
            }
        } catch (\Exception $e) {
            debugging("local_certificatesign observer: {$e->getMessage()}", DEBUG_DEVELOPER);
        }
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
