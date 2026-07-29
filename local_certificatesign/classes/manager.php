<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

class manager {

    private static $signing = false;

    public static function is_configured(): bool {
        if (!get_config('local_certificatesign', 'autosign_enabled')) {
            return false;
        }

        $pfxcontent = signer::get_pfx_content();
        $password = get_config('local_certificatesign', 'certpassword');

        return $pfxcontent !== null && !empty($password);
    }

    public static function log_table_exists(): bool {
        global $DB;

        $dbman = $DB->get_manager();
        return $dbman->table_exists(new \xmldb_table('local_certificatesign_log'));
    }

    public static function already_signed(int $issueid): bool {
        global $DB;

        return $DB->record_exists('local_certificatesign_log', ['issueid' => $issueid]);
    }

    public static function audit_access(\stdClass $issue, string $action): void {
        global $DB;

        try {
            $allowed = ['view', 'download', 'token_view', 'pending'];
            if (!in_array($action, $allowed, true)) {
                return;
            }

            $userid = (int)($issue->userid ?? 0);
            $issueid = (int)($issue->id ?? 0);
            $cmid = (int)($issue->cmid ?? 0);

            if (!$userid || !$issueid || !$cmid) {
                return;
            }

            $cm = get_coursemodule_from_id('certificatebeautiful', $cmid);
            $courseid = $cm ? (int)$cm->course : 0;

            $DB->insert_record('local_certificatesign_audit', (object)[
                'userid' => $userid,
                'issueid' => $issueid,
                'cmid' => $cmid,
                'courseid' => $courseid,
                'action' => $action,
                'timecreated' => time(),
                'ipaddress' => $_SERVER['REMOTE_ADDR'] ?? '',
                'useragent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (\Throwable $e) {
            debugging('local_certificatesign: audit_access failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    public static function is_signed(int $issueid): bool {
        if (!self::log_table_exists()) {
            return false;
        }
        if (!self::already_signed($issueid)) {
            return false;
        }
        return self::is_signed_file_exists($issueid);
    }

    public static function is_signed_file_exists(int $issueid): bool {
        global $DB;

        $issue = $DB->get_record('certificatebeautiful_issue', ['id' => $issueid]);
        if (!$issue) {
            return false;
        }

        $cm = get_coursemodule_from_id('certificatebeautiful', $issue->cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return false;
        }

        $context = \context_module::instance($cm->id, IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_certificatebeautiful', 'certificate', $issue->userid, '/', $issue->code . '.pdf');
        if (!$file) {
            return false;
        }

        $content = $file->get_content();
        if ($content === false || $content === '') {
            return false;
        }

        return strpos($content, '/ByteRange') !== false;
    }

    public static function sign_issue(\stdClass $issue): bool {
        global $DB;

        if (self::$signing || !self::is_configured() || !self::log_table_exists()) {
            return false;
        }
        if (empty($issue->id) || self::already_signed((int)$issue->id)) {
            return false;
        }

        $cm = get_coursemodule_from_id('certificatebeautiful', $issue->cmid);
        if (!$cm) {
            return false;
        }

        $context = \context_module::instance($cm->id);
        $filename = $issue->code . '.pdf';
        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_certificatebeautiful', 'certificate', $issue->userid, '/', $filename);
        if (!$file) {
            return false;
        }

        self::$signing = true;
        try {
            $originalcontent = $file->get_content();
            $signedpdf = signer::sign_pdf($originalcontent);
            $filerecord = [
                'contextid' => $file->get_contextid(),
                'component' => $file->get_component(),
                'filearea' => $file->get_filearea(),
                'itemid' => $file->get_itemid(),
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            ];

            $deleted = false;
            try {
                $file->delete();
                $deleted = true;
                $fs->create_file_from_string($filerecord, $signedpdf);
            } catch (\Throwable $e) {
                if ($deleted) {
                    try {
                        $fs->create_file_from_string($filerecord, $originalcontent);
                    } catch (\Throwable $restoreexception) {
                        debugging('local_certificatesign: could not restore original PDF: ' . $restoreexception->getMessage(), DEBUG_DEVELOPER);
                    }
                }
                throw $e;
            }

            $DB->insert_record('local_certificatesign_log', (object)[
                'issueid' => $issue->id,
                'timecreated' => time(),
            ]);

            return true;
        } finally {
            self::$signing = false;
        }
    }
}
