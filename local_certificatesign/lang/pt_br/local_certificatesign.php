<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'Assinatura Digital de Certificados';
$string['settings']                = 'Configurações de Assinatura';
$string['pfxfile']                 = 'Certificado PFX/P12';
$string['pfxfile_help']            = 'Faça upload do arquivo PFX ou P12. Após enviar, informe a senha abaixo e salve.';
$string['certpassword']            = 'Senha do Certificado';
$string['certpassword_help']       = 'Senha para desbloquear o certificado PFX/P12.';
$string['signerreason']            = 'Motivo da Assinatura';
$string['signerreason_help']       = 'Motivo exibido na assinatura (ex.: "Certificado de Curso").';
$string['autosign_enabled']        = 'Habilitar/desabilitar a tarefa agendada';
$string['autosign_enabled_help']   = 'Quando habilitado, a tarefa agendada processa certificados pendentes.';
$string['task_interval']           = 'local_certificatesign | task_interval';
$string['task_interval_help']      = 'A cada quantos minutos a tarefa processa certificados pendentes. Padrão: 2 minutos.';
$string['task_sign']               = 'Assinar certificados pendentes';
$string['privacy:metadata']        = 'Este plugin não armazena dados pessoais diretamente.';
$string['errorreadingpfx']         = 'Erro ao ler o certificado PFX/P12. Verifique a senha.';
$string['erroropenssl']            = 'Erro OpenSSL: {$a}';
$string['invalidpdf']              = 'Conteúdo PDF inválido.';
$string['notconfigured']           = 'Assinatura digital não configurada.';
