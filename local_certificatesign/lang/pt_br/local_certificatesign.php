<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'Assinatura Digital de Certificados';
$string['settings']                = 'Configurações de Assinatura';

$string['pfxfile']                 = 'Certificado PFX/P12';
$string['pfxfile_help']            = 'Faça upload do arquivo PFX ou P12 usado para assinar digitalmente os certificados.';
$string['certpassword']            = 'Senha do Certificado';
$string['certpassword_help']       = 'Senha para desbloquear o certificado PFX/P12.';
$string['signername']              = 'Nome do Signatário';
$string['signername_help']         = 'Nome exibido como signatário do certificado.';
$string['signerlocation']          = 'Local do Signatário';
$string['signerlocation_help']     = 'Cidade/local do signatário.';
$string['signerreason']            = 'Motivo da Assinatura';
$string['signerreason_help']       = 'Motivo exibido na assinatura (ex.: "Certificado de Curso").';
$string['signercontact']           = 'Informações de Contato';
$string['signercontact_help']      = 'Contato do signatário (ex.: e-mail ou telefone).';

$string['task_sign']               = 'Assinar certificados pendentes';
$string['signature_appended']      = 'Certificado assinado digitalmente.';

$string['privacy:metadata']        = 'Este plugin não armazena dados pessoais diretamente.';

$string['errorreadingpfx']         = 'Erro ao ler o certificado PFX/P12. Verifique a senha.';
$string['erroropenssl']            = 'Erro OpenSSL: {$a}';
$string['invalidpdf']              = 'Conteúdo PDF inválido.';
$string['invalidpfx']              = 'Certificado PFX/P12 inválido ou corrompido.';
$string['notconfigured']           = 'Assinatura digital não configurada. Faça upload do certificado PFX nas configurações do plugin.';
