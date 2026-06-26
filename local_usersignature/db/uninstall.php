<?php
/**
 * Executado pelo Moodle ANTES de remover as tabelas do banco.
 * Limpa todos os arquivos de assinatura armazenados no file_storage
 * para não deixar arquivos órfãos em moodledata.
 */
function xmldb_local_usersignature_uninstall(): bool {
    global $DB;

    $fs = get_file_storage();

    // Obter todos os usuários que possuem assinatura cadastrada.
    $records = $DB->get_records('local_usersignature', [], '', 'userid');

    foreach ($records as $record) {
        try {
            $context = \core\context\user::instance($record->userid, IGNORE_MISSING);
            if ($context) {
                $fs->delete_area_files($context->id, 'local_usersignature', 'signature');
            }
        } catch (\Exception $e) {
            // Contexto pode não existir se o usuário foi deletado — ignorar.
            debugging('local_usersignature uninstall: falha ao limpar usuário ' . $record->userid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // A tabela local_usersignature será removida automaticamente pelo Moodle
    // após este script retornar true, com base no install.xml.

    return true;
}
