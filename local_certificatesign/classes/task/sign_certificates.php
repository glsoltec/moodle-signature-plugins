<?php
namespace local_certificatesign\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that signs pending certificate PDFs.
 *
 * Looks for certificates issued by mod_certificatebeautiful that have not
 * been signed yet, signs them, and stores the signed version back.
 */
class sign_certificates extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_sign', 'local_certificatesign');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $DB;

        if (!self::is_enabled()) {
            mtrace('local_certificatesign: automatic signing disabled in settings, skipping.');
            return;
        }

        $interval = (int) get_config('local_certificatesign', 'task_interval');
        if ($interval <= 0) {
            $interval = 2;
        }
        $lastrun = (int) get_config('local_certificatesign', 'task_lastrun');
        if ($lastrun > 0 && (time() - $lastrun) < $interval * 60) {
            return;
        }
        set_config('task_lastrun', time(), 'local_certificatesign');

        $fs = get_file_storage();

        $sql = "SELECT ci.id, ci.userid, ci.cmid, ci.code, ci.certificatebeautifulid, ci.timecreated
                  FROM {certificatebeautiful_issue} ci
             LEFT JOIN {local_certificatesign_log} l ON l.issueid = ci.id
                 WHERE l.id IS NULL";
        $issues = $DB->get_records_sql($sql);

        if (empty($issues)) {
            mtrace('local_certificatesign: no pending certificates to sign.');
            return;
        }

        $count = 0;
        foreach ($issues as $issue) {
            try {
                $cm = get_coursemodule_from_id('certificatebeautiful', $issue->cmid);
                if (!$cm) {
                    continue;
                }

                $context = \context_module::instance($cm->id);
                $filename = "{$issue->code}.pdf";

                $file = $fs->get_file(
                    $context->id,
                    'mod_certificatebeautiful',
                    'certificate',
                    $issue->userid,
                    '/',
                    $filename
                );

                if (!$file) {
                    continue;
                }

                $pdfcontent = $file->get_content();
                $signedpdf = \local_certificatesign\signer::sign_pdf($pdfcontent);

                $file->delete();
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_certificatebeautiful',
                    'filearea'  => 'certificate',
                    'itemid'    => $issue->userid,
                    'filepath'  => '/',
                    'filename'  => $filename,
                ], $signedpdf);

                self::log_signed($issue->id);

                $count++;
                mtrace("local_certificatesign: signed issue {$issue->id} ({$filename})");
            } catch (\Exception $e) {
                mtrace("local_certificatesign: error signing issue {$issue->id}: {$e->getMessage()}");
            }
        }

        mtrace("local_certificatesign: {$count} certificate(s) signed.");
    }

    /**
     * Check if automatic signing is enabled and PFX is configured.
     */
    public static function is_enabled(): bool {
        if (!get_config('local_certificatesign', 'autosign_enabled')) {
            return false;
        }
        $pfxcontent = \local_certificatesign\signer::get_pfx_content();
        $password = get_config('local_certificatesign', 'certpassword');
        return $pfxcontent !== null && !empty($password);
    }

    /**
     * Log that an issue has been signed.
     */
    private static function log_signed(int $issueid): void {
        global $DB;

        // Create log table on first use if not exists.
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

        $DB->insert_record('local_certificatesign_log', (object)[
            'issueid'     => $issueid,
            'timecreated' => time(),
        ]);
    }
}
