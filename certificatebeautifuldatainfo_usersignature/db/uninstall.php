<?php
/**
 * Executado pelo Moodle antes de remover o subplugin.
 * Este plugin não possui tabelas próprias — apenas garante
 * que o subplugin seja desabilitado de forma limpa antes da remoção.
 */
function xmldb_certificatebeautifuldatainfo_usersignature_uninstall(): bool {
    // Nenhuma tabela para limpar.
    // Os arquivos de assinatura pertencem ao local_usersignature e não são afetados.
    return true;
}
