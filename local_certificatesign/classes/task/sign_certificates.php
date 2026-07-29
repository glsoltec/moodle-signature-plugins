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
                            self::send_release_email($issue);
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

    private static function send_release_email(\stdClass $issue): void {
        global $DB;

        $logrecord = $DB->get_record('local_certificatesign_log', ['issueid' => $issue->id]);
        if (!$logrecord || $logrecord->email_sent) {
            return;
        }

        $user = \core_user::get_user($issue->userid, '*', IGNORE_MISSING);
        if (!$user || $user->deleted || $user->suspended) {
            return;
        }

        $cm = get_coursemodule_from_id('certificatebeautiful', $issue->cmid, 0, false, IGNORE_MISSING);
        $course = $cm ? $DB->get_record('course', ['id' => $cm->course], '*', IGNORE_MISSING) : null;
        $certificatebeautiful = $cm ? $DB->get_record('certificatebeautiful', ['id' => $cm->instance], '*', IGNORE_MISSING) : null;

        if (!$course || !$certificatebeautiful || empty($certificatebeautiful->notifyuser)) {
            return;
        }

        $data = (object)[
            'fullname' => fullname($user),
            'certificatename' => format_string($certificatebeautiful->name),
            'coursename' => format_string($course->fullname),
            'url' => (new \moodle_url('/mod/certificatebeautiful/view.php', ['id' => $cm->id]))->out(false),
        ];

        $supportuser = \core_user::get_support_user();
        $subject = get_string('notification_subject', 'certificatebeautiful', $data);
        $messagehtml = get_string('notification_body', 'certificatebeautiful', $data);
        $messageplain = html_to_text($messagehtml);

        $update = (object)[
            'id' => $logrecord->id,
            'email_attempts' => $logrecord->email_attempts + 1,
        ];

        try {
            $sent = email_to_user($user, $supportuser, $subject, $messageplain, $messagehtml);
            if ($sent) {
                $update->email_sent = 1;
                $update->email_time = time();
                $update->email_last_error = null;
                mtrace("local_certificatesign: release email sent to user {$user->id} for issue {$issue->id}");
            } else {
                $update->email_last_error = 'email_to_user returned false';
                mtrace("local_certificatesign: email_to_user failed for issue {$issue->id}");
            }
        } catch (\Throwable $e) {
            $update->email_last_error = substr($e->getMessage(), 0, 255);
            mtrace("local_certificatesign: email error for issue {$issue->id}: {$e->getMessage()}");
        }

        $DB->update_record('local_certificatesign_log', $update);
    }
}
