<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_usersignature\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_user;

/**
 * Privacy provider para local_usersignature.
 *
 * Armazena, por usuário: metadados na tabela {local_usersignature} e o
 * arquivo PNG da assinatura na file area 'signature' (componente
 * 'local_usersignature') no contexto de usuário.
 *
 * @package local_usersignature
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Descreve os dados pessoais armazenados pelo plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_usersignature',
            [
                'userid'         => 'privacy:metadata:local_usersignature:userid',
                'font_style'     => 'privacy:metadata:local_usersignature:font_style',
                'signature_text' => 'privacy:metadata:local_usersignature:signature_text',
                'timecreated'    => 'privacy:metadata:local_usersignature:timecreated',
                'timemodified'   => 'privacy:metadata:local_usersignature:timemodified',
            ],
            'privacy:metadata:local_usersignature'
        );

        $collection->add_subsystem_link(
            'core_files',
            [],
            'privacy:metadata:filearea:signature'
        );

        return $collection;
    }

    /**
     * Lista os contextos que contêm dados do usuário.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Os dados ficam sempre no contexto do próprio usuário.
        $sql = "SELECT ctx.id
                  FROM {local_usersignature} sig
                  JOIN {context} ctx
                    ON ctx.instanceid = sig.userid
                   AND ctx.contextlevel = :contextlevel
                 WHERE sig.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Lista os usuários que possuem dados em um dado contexto.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }

        if (self::user_has_signature($context->instanceid)) {
            $userlist->add_user($context->instanceid);
        }
    }

    /**
     * Exporta todos os dados do usuário nos contextos aprovados.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || $context->instanceid != $user->id) {
                continue;
            }

            $record = $DB->get_record('local_usersignature', ['userid' => $user->id]);
            if (!$record) {
                continue;
            }

            $data = (object) [
                'font_style'     => $record->font_style,
                'signature_text' => $record->signature_text,
                'timecreated'    => \core_privacy\local\request\transform::datetime($record->timecreated),
                'timemodified'   => \core_privacy\local\request\transform::datetime($record->timemodified),
            ];

            $subcontext = [get_string('pluginname', 'local_usersignature')];
            writer::with_context($context)->export_data($subcontext, $data);

            // Exporta o arquivo PNG da assinatura.
            writer::with_context($context)->export_area_files(
                $subcontext, 'local_usersignature', 'signature', 0
            );
        }
    }

    /**
     * Remove todos os dados de todos os usuários no contexto informado.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_user) {
            return;
        }

        $DB->delete_records('local_usersignature', ['userid' => $context->instanceid]);

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_usersignature', 'signature');
    }

    /**
     * Remove os dados do usuário nos contextos aprovados.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || $context->instanceid != $user->id) {
                continue;
            }

            $DB->delete_records('local_usersignature', ['userid' => $user->id]);

            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'local_usersignature', 'signature');
        }
    }

    /**
     * Remove os dados dos usuários aprovados em um contexto.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            if ($userid != $context->instanceid) {
                continue;
            }

            $DB->delete_records('local_usersignature', ['userid' => $userid]);

            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'local_usersignature', 'signature');
        }
    }

    /**
     * Verifica se há assinatura cadastrada para o usuário.
     *
     * @param int $userid
     * @return bool
     */
    private static function user_has_signature(int $userid): bool {
        global $DB;
        return $DB->record_exists('local_usersignature', ['userid' => $userid]);
    }
}
