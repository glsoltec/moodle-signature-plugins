<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Executado automaticamente pelo Moodle ao detectar que a versão instalada
 * é menor do que a declarada em version.php.
 */
function xmldb_local_usersignature_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Exemplo de migração futura:
    // if ($oldversion < 2025060100) {
    //     $table = new xmldb_table('local_usersignature');
    //     $field = new xmldb_field('new_column', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
    //     if (!$dbman->field_exists($table, $field)) {
    //         $dbman->add_field($table, $field);
    //     }
    //     upgrade_plugin_savepoint(true, 2025060100, 'local', 'usersignature');
    // }

    return true;
}
