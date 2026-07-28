<?php
namespace local_certificatesign;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function file_created(\core\event\file_created $event): void {
        global $DB;

        $data = $event->get_data();
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($data['objectid']);
        if (!$file) {
            return;
        }

        if ($file->get_component() !== 'mod_certificatebeautiful' || $file->get_filearea() !== 'certificate') {
            return;
        }

        $issuecode = basename($file->get_filename(), '.pdf');
        $issue = $DB->get_record('certificatebeautiful_issue', ['code' => $issuecode]);
        if (!$issue) {
            return;
        }

        try {
            manager::sign_issue($issue);
        } catch (\Throwable $e) {
            debugging('local_certificatesign observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
