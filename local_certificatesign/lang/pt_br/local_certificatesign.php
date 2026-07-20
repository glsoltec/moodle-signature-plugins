<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'Assinatura Digital de Certificados';
$string['settings']                = 'Configurações de Assinatura';

$string['pfxfile']                 = 'Certificado PFX/P12';
$string['pfxfile_help']            = 'Faça upload do arquivo PFX ou P12. Após enviar, informe a senha abaixo e salve. Se a senha estiver correta, os dados do dono do certificado serão exibidos.';
$string['certpassword']            = 'Senha do Certificado';
$string['certpassword_help']       = 'Senha para desbloquear o certificado PFX/P12. Salve o formulário para validar.';

$string['certinfo']                = 'Informações do Certificado';
$string['certinfo_help']           = 'Informações extraídas do certificado após validação da senha.';
$string['certinfonocert']          = 'Nenhum certificado enviado ou senha não configurada. Envie um arquivo PFX e informe a senha, depois salve.';
$string['certinfo_cn']             = 'Proprietário (CN)';
$string['certinfo_org']            = 'Organização';
$string['certinfo_valid']          = 'Validade';
$string['certinfo_issuer']         = 'Emissor';
$string['certexpired']             = 'Este certificado está expirado!';

$string['signerreason']            = 'Motivo da Assinatura';
$string['signerreason_help']       = 'Motivo exibido na assinatura (ex.: "Certificado de Curso").';
$string['autosign_enabled']        = 'Habilitar/desabilitar a tarefa agendada';
$string['autosign_enabled_help']   = 'Quando habilitado, a tarefa agendada processa certificados pendentes e o observer assina imediatamente na emissão. Quando desabilitado, a tarefa não executa e nenhum certificado é assinado automaticamente.';
$string['task_interval']           = 'local_certificatesign | task_interval';
$string['task_interval_help']      = 'Define a cada quantos minutos a tarefa agendada processa os certificados pendentes. Padrão: 2 minutos.';

$string['gen_heading']             = 'Deseja gerar um novo certificado autoassinado?';
$string['gen_heading_desc']        = 'Caso não tenha um arquivo PFX/P12, o sistema pode gerar um certificado autoassinado com validade de 10 anos. Preencha os dados abaixo e ele será instalado automaticamente como certificado do plugin.';
$string['gen_btn']                 = 'Gerar Certificado Autoassinado';
$string['gen_title']               = 'Gerar Certificado Autoassinado';
$string['gen_cn']                  = 'Nome Comum (CN)';
$string['gen_org']                 = 'Organização';
$string['gen_country']             = 'País (código de 2 letras)';
$string['gen_password']            = 'Senha do Certificado';
$string['gen_password_confirm']    = 'Confirmar Senha';
$string['gen_generate']            = 'Gerar e Instalar';
$string['gen_passwords_mismatch']  = 'As senhas não conferem.';
$string['gen_password_weak']       = 'A senha deve ter no mínimo 4 caracteres.';
$string['gen_success']             = 'Certificado autoassinado gerado e instalado com sucesso.';

$string['task_sign']               = 'Assinar certificados pendentes';
$string['signature_appended']      = 'Certificado assinado digitalmente.';

$string['privacy:metadata']        = 'Este plugin não armazena dados pessoais diretamente.';

$string['errorreadingpfx']         = 'Erro ao ler o certificado PFX/P12. Verifique a senha.';
$string['erroropenssl']            = 'Erro OpenSSL: {$a}';
$string['invalidpdf']              = 'Conteúdo PDF inválido.';
$string['invalidpfx']              = 'Certificado PFX/P12 inválido ou corrompido.';
$string['notconfigured']           = 'Assinatura digital não configurada. Faça upload do certificado PFX nas configurações do plugin.';
