<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Executado automaticamente pelo Moodle ao detectar que a versão instalada
 * é menor do que a declarada em version.php.
 */
function xmldb_local_usersignature_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2.1.0 — troca das fontes (Google Fonts → fontes locais) e novo padrão.
    if ($oldversion < 2026070200) {
        // Novo default do campo font_style.
        $table = new xmldb_table('local_usersignature');
        $field = new xmldb_field('font_style', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'autography');
        $dbman->change_field_default($table, $field);

        // Migra assinaturas com fontes antigas para a nova fonte padrão.
        // O PNG salvo continua válido; ao regravar, o usuário usa as novas fontes.
        list($insql, $params) = $DB->get_in_or_equal(['aerotis', 'autography', 'creata', 'tomatoes'], SQL_PARAMS_QM, 'param', false);
        $DB->set_field_select('local_usersignature', 'font_style', 'autography', "font_style $insql", $params);

        upgrade_plugin_savepoint(true, 2026070200, 'local', 'usersignature');
    }

    return true;
}
