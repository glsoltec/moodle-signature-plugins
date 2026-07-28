# Plano de correções  moodle-signature-plugins

## Escopo

Este repositório controla assinatura digital, tarefas agendadas, fonte da assinatura e dados auxiliares.

As correções devem ser aplicadas uma por vez.

## Etapa 1  Criar estrutura de auditoria

Criar a tabela `local_certificatesign_audit` com:

- `id`;
- `userid`;
- `issueid`;
- `cmid`;
- `courseid`;
- `action`;
- `timecreated`;
- `ipaddress`;
- `useragent`.

Criar índices para:

- `userid`;
- `issueid`;
- `courseid`.

Alterar:

- `local_certificatesign/db/install.xml`;
- `local_certificatesign/db/upgrade.php`;
- `local_certificatesign/version.php`.

A migração deve:

- ser idempotente;
- verificar se tabela e campos existem;
- nunca apagar dados;
- usar versão superior à instalada;
- criar savepoint Moodle.

Adicionar ao manager:

```php
public static function audit_access(stdClass $issue, string $action): void
```

A auditoria não pode interromper a entrega do certificado.

## Etapa 2  E-mail após assinatura

Adicionar na tabela de assinatura:

- `email_sent`;
- `email_time`;
- `email_attempts`;
- `email_last_error`.

Fluxo:

1. tarefa encontra PDF pendente;
2. assina o PDF;
3. confirma o arquivo assinado;
4. grava `signed`;
5. envia e-mail;
6. marca e-mail enviado somente após sucesso;
7. grava `released`.

Falhas devem permanecer pendentes para retry.

Não enviar confirmação de liberação durante a geração inicial.

Usar `email_to_user()`.

Não incluir token permanente, senha PFX ou dados sensíveis no e-mail.

## Etapa 3  Controle de liberação

O manager deve oferecer uma verificação inequívoca:

```php
public static function is_signed(int $issueid): bool
```

A verificação deve considerar:

- registro da assinatura;
- arquivo PDF existente;
- assinatura criptográfica válida, quando a verificação estiver disponível.

## Etapa 4  Privacidade

Atualizar o provider do plugin para declarar:

- registros de auditoria;
- usuário;
- certificado;
- curso;
- IP;
- user-agent;
- data;
- ação;
- status de envio.

Implementar exportação e exclusão conforme as APIs Moodle.

## Segurança

- nunca registrar senha do PFX;
- nunca registrar conteúdo do PFX;
- não registrar token completo;
- validar IDs como inteiros;
- limitar strings;
- usar APIs Moodle de banco;
- usar lock na tarefa;
- evitar duplicidade com atualização condicional;
- restaurar o PDF original se a substituição falhar;
- não liberar PDF se a assinatura falhar.

## Validação

Executar:

```bash
php -l local_certificatesign/classes/manager.php
php -l local_certificatesign/classes/task/sign_certificates.php
php -l local_certificatesign/db/upgrade.php
php -l local_certificatesign/version.php
git diff --check
```

No Moodle:

```bash
php admin/cli/upgrade.php
php admin/cli/cron.php
php admin/cli/scheduled_task.php --execute='\\local_certificatesign\\task\\sign_certificates'
```

Testar:

1. PDF pendente não é liberado.
2. Tarefa assina o PDF.
3. Registro `signed` é criado.
4. E-mail é enviado uma única vez.
5. Registro `released` é criado.
6. Falha de e-mail gera retry.
7. Execução repetida não duplica assinatura nem e-mail.
8. Auditoria registra usuário, curso e emissão.
9. PDF mantém validade e assinatura criptográfica.

## Commits sugeridos

Etapa 1:

```
Add certificate audit storage
```

Etapa 2:

```
Send release email after digital signing
```

Etapa 3:

```
Require signed certificate before delivery
```
