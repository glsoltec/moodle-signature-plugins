<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_certificatesign_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026071800) {
        $table = new \xmldb_table('local_certificatesign_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('issueid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('issueid', XMLDB_KEY_UNIQUE, ['issueid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026071800, 'local_certificatesign');
    }

    if ($oldversion < 2026071802) {
        upgrade_plugin_savepoint(true, 2026071802, 'local_certificatesign');
    }

    if ($oldversion < 2026071803) {
        upgrade_plugin_savepoint(true, 2026071803, 'local_certificatesign');
    }

    return true;
}
