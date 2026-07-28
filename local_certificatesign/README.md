# local_certificatesign

Assina PDFs emitidos pelo `mod_certificatebeautiful` usando TCPDF + FPDI.

## Dependência

Antes de rodar o upgrade em produção:

```bash
cd /var/www/moodle/local/certificatesign
composer install --no-dev --optimize-autoloader
```

O TCPDF vem do core do Moodle. A FPDI é instalada localmente neste plugin via Composer.

## Upgrade seguro

```bash
cd /var/www/moodle
sudo -u www-data php admin/cli/maintenance.php --enable
sudo -u www-data php admin/cli/upgrade.php --non-interactive
sudo -u www-data php admin/cli/purge_caches.php
sudo -u www-data php admin/cli/maintenance.php --disable
```

## Observações

- A tarefa agendada roda por padrão a cada 5 minutos e serve como retaguarda do observer.
- A frequência deve ser ajustada na tela nativa de tarefas agendadas do Moodle.
- A FPDI livre pode falhar em PDFs com object streams. Se ocorrer, normalize o PDF antes da assinatura ou avalie FPDI PDF-Parser comercial.