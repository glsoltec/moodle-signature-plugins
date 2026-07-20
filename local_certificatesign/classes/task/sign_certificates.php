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

        $dbman = $DB->get_manager();
        $logtable = new \xmldb_table('local_certificatesign_log');
        if (!$dbman->table_exists($logtable)) {
            mtrace('local_certificatesign: log table not found. Run upgrade first.');
            return;
        }

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

                $DB->insert_record('local_certificatesign_log', (object)[
                    'issueid'     => $issue->id,
                    'timecreated' => time(),
                ]);

                $count++;
                mtrace("local_certificatesign: signed issue {$issue->id} ({$filename})");
            } catch (\Exception $e) {
                mtrace("local_certificatesign: error signing issue {$issue->id}: {$e->getMessage()}");
            }
        }

        mtrace("local_certificatesign: {$count} certificate(s) signed.");
    }

    public static function is_enabled(): bool {
        if (!get_config('local_certificatesign', 'autosign_enabled')) {
            return false;
        }
        $pfxcontent = \local_certificatesign\signer::get_pfx_content();
        $password = get_config('local_certificatesign', 'certpassword');
        return $pfxcontent !== null && !empty($password);
    }
}
