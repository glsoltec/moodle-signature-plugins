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
        upgrade_plugin_savepoint(true, 2026071800, 'local', 'certificatesign');
    }

    if ($oldversion < 2026071802) {
        upgrade_plugin_savepoint(true, 2026071802, 'local', 'certificatesign');
    }

    if ($oldversion < 2026071803) {
        upgrade_plugin_savepoint(true, 2026071803, 'local', 'certificatesign');
    }
    if ($oldversion < 2026072701) {
        unset_config('task_interval', 'local_certificatesign');
        unset_config('task_lastrun', 'local_certificatesign');
        upgrade_plugin_savepoint(true, 2026072701, 'local', 'certificatesign');
    }
    if ($oldversion < 2026072800) {
        upgrade_plugin_savepoint(true, 2026072800, 'local', 'certificatesign');
    }

    if ($oldversion < 2026072900) {
        $table = new \xmldb_table('local_certificatesign_audit');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('issueid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, '0');
            $table->add_field('ipaddress', XMLDB_TYPE_CHAR, '45', null, null, null, '');
            $table->add_field('useragent', XMLDB_TYPE_CHAR, '255', null, null, null, '');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('issueid', XMLDB_INDEX_NOTUNIQUE, ['issueid']);
            $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026072900, 'local', 'certificatesign');
    }

    if ($oldversion < 2026073000) {
        $table = new \xmldb_table('local_certificatesign_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('issueid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, '0');
            $table->add_field('email_sent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, '0');
            $table->add_field('email_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, '0');
            $table->add_field('email_attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, '0');
            $table->add_field('email_last_error', XMLDB_TYPE_CHAR, '255', null, null, null, '');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('issueid', XMLDB_KEY_UNIQUE, ['issueid']);
            $dbman->create_table($table);
        } else {
            $fields = [
                new \xmldb_field('email_sent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timecreated'),
                new \xmldb_field('email_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'email_sent'),
                new \xmldb_field('email_attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'email_time'),
                new \xmldb_field('email_last_error', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'email_attempts'),
            ];
            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026073000, 'local', 'certificatesign');
    }

    return true;
}
