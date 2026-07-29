<?php
namespace local_certificatesign\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;
use context_user;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_certificatesign_audit',
            [
                'userid'      => 'privacy:metadata:local_certificatesign_audit:userid',
                'issueid'     => 'privacy:metadata:local_certificatesign_audit:issueid',
                'cmid'        => 'privacy:metadata:local_certificatesign_audit:cmid',
                'courseid'    => 'privacy:metadata:local_certificatesign_audit:courseid',
                'action'      => 'privacy:metadata:local_certificatesign_audit:action',
                'timecreated' => 'privacy:metadata:local_certificatesign_audit:timecreated',
                'ipaddress'   => 'privacy:metadata:local_certificatesign_audit:ipaddress',
                'useragent'   => 'privacy:metadata:local_certificatesign_audit:useragent',
            ],
            'privacy:metadata:local_certificatesign_audit'
        );

        $collection->add_database_table(
            'local_certificatesign_log',
            [
                'issueid'     => 'privacy:metadata:local_certificatesign_log:issueid',
                'timecreated' => 'privacy:metadata:local_certificatesign_log:timecreated',
            ],
            'privacy:metadata:local_certificatesign_log'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {local_certificatesign_audit} a
                  JOIN {context} ctx
                    ON ctx.instanceid = a.userid
                   AND ctx.contextlevel = :contextlevel
                 WHERE a.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid'       => $userid,
        ]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        global $DB;
        if ($DB->record_exists('local_certificatesign_audit', ['userid' => $context->instanceid])) {
            $userlist->add_user($context->instanceid);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int)$context->instanceid !== (int)$user->id) {
                continue;
            }
            $records = $DB->get_records('local_certificatesign_audit', ['userid' => $user->id], 'timecreated DESC');
            if (!$records) {
                continue;
            }
            $exportdata = [];
            foreach ($records as $record) {
                $exportdata[] = (object)[
                    'action'      => $record->action,
                    'issueid'     => $record->issueid,
                    'cmid'        => $record->cmid,
                    'courseid'    => $record->courseid,
                    'timecreated' => transform::datetime($record->timecreated),
                    'ipaddress'   => $record->ipaddress,
                    'useragent'   => $record->useragent,
                ];
            }
            $subcontext = [get_string('pluginname', 'local_certificatesign')];
            writer::with_context($context)->export_data($subcontext, (object)['audit_records' => $exportdata]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int)$context->instanceid !== (int)$user->id) {
                continue;
            }
            $DB->execute(
                "UPDATE {local_certificatesign_audit}
                    SET userid = 0, ipaddress = '', useragent = ''
                  WHERE userid = ?",
                [$user->id]
            );
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->execute(
            "UPDATE {local_certificatesign_audit}
                SET userid = 0, ipaddress = '', useragent = ''
              WHERE userid {$insql}",
            $inparams
        );
    }
}
