<?php
namespace local_certificatesign\task;

defined('MOODLE_INTERNAL') || die();

class sign_certificates extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_sign', 'local_certificatesign');
    }

    public function execute(): void {
        global $DB;

        mtrace('local_certificatesign: task started.');

        if (!\local_certificatesign\manager::is_configured()) {
            mtrace('local_certificatesign: automatic signing disabled or certificate not configured, skipping.');
            return;
        }
        if (!\local_certificatesign\manager::log_table_exists()) {
            mtrace('local_certificatesign: log table not found. Run upgrade.php first.');
            return;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_certificatesign');
        $lock = $lockfactory->get_lock('sign_certificates', 0);
        if (!$lock) {
            mtrace('local_certificatesign: another signing task is already running, skipping.');
            return;
        }

        try {
            $sql = "SELECT ci.id, ci.userid, ci.cmid, ci.code, ci.certificatebeautifulid, ci.timecreated
                      FROM {certificatebeautiful_issue} ci
                 LEFT JOIN {local_certificatesign_log} l ON l.issueid = ci.id
                     WHERE l.id IS NULL";
            $issues = $DB->get_recordset_sql($sql);

            $count = 0;
            try {
                foreach ($issues as $issue) {
                    try {
                        if (\local_certificatesign\manager::sign_issue($issue)) {
                            $count++;
                            mtrace("local_certificatesign: signed issue {$issue->id} ({$issue->code}.pdf)");
                        }
                    } catch (\Throwable $e) {
                        mtrace("local_certificatesign: error signing issue {$issue->id}: {$e->getMessage()}");
                    }
                }
            } finally {
                $issues->close();
            }

            mtrace("local_certificatesign: {$count} certificate(s) signed.");
        } finally {
            $lock->release();
        }
    }
}
