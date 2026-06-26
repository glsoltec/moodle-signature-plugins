# moodle-signature-plugins

Plugins de **assinatura cursiva por usuário** para Moodle 5.x, integrados com o [`mod_certificatebeautiful`](https://github.com/EduardoKrausME/moodle-mod_certificatebeautiful).

---

## Plugins incluídos

| Diretório | Tipo | Função |
|-----------|------|--------|
| `local_usersignature` | `local` | Gerencia a assinatura do usuário (seleção de fonte + salvar PNG) |
| `certificatebeautifuldatainfo_usersignature` | `certificatebeautifuldatainfo` | Expõe tags `{$USERSIGNATURE->...}` no editor de certificados |

---

## Requisitos

- Moodle **5.0** ou superior (testado em 5.2.1)
- PHP **8.2+**
- Plugin [`mod_certificatebeautiful`](https://github.com/EduardoKrausME/moodle-mod_certificatebeautiful) instalado (para o subplugin de certificado)
- Acesso à internet no servidor para carregar Google Fonts (Dancing Script, Great Vibes, Satisfy, Caveat)

---

## Instalação — Interface Web (recomendado)

### 1. Preparar os arquivos ZIP

```bash
# Criar os ZIPs a partir deste repositório
git clone https://github.com/glsoltec/moodle-signature-plugins.git
cd moodle-signature-plugins

zip -r local_usersignature.zip local_usersignature/
zip -r certificatebeautifuldatainfo_usersignature.zip certificatebeautifuldatainfo_usersignature/
```

### 2. Instalar via Administração do Moodle

1. Acesse **Administração do site → Plugins → Instalar plugins**
2. Faça upload do `local_usersignature.zip` → clique **Instalar plugin**
3. Siga o assistente de instalação e confirme a criação da tabela `mdl_local_usersignature`
4. Repita para `certificatebeautifuldatainfo_usersignature.zip`
5. Clique **Atualizar banco de dados do Moodle** ao final

### 3. Verificar instalação

- **Administração → Plugins → Visão geral dos plugins**
  - Confirmar `local_usersignature` (versão 2.0.0) como **Instalado**
  - Confirmar `certificatebeautifuldatainfo_usersignature` (versão 2.0.0) como **Instalado**

---

## Instalação — Linha de Comando (servidores sem acesso web ao admin)

```bash
# Transferir para o servidor
scp local_usersignature.zip usuario@servidor:/tmp/
scp certificatebeautifuldatainfo_usersignature.zip usuario@servidor:/tmp/

# Extrair nos diretórios corretos
sudo unzip /tmp/local_usersignature.zip -d /var/www/moodle/local/
sudo unzip /tmp/certificatebeautifuldatainfo_usersignature.zip \
    -d /var/www/moodle/mod/certificatebeautiful/plugins_datainfo/

# Corrigir permissões (ajustar usuário conforme seu servidor)
sudo chown -R www-data:www-data /var/www/moodle/local/usersignature
sudo chown -R www-data:www-data /var/www/moodle/mod/certificatebeautiful/plugins_datainfo/usersignature

# Executar upgrade
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

---

## Como usar

### Para o usuário (aluno ou professor)

1. Acesse seu **Perfil → Minha Assinatura**
2. Digite o nome como deseja que apareça na assinatura
3. Escolha entre os 4 estilos cursivos disponíveis (pré-visualização ao vivo)
4. Clique em **Salvar Assinatura**

### Estilos de fonte disponíveis

| Estilo | Fonte | Característica |
|--------|-------|----------------|
| `dancing` | Dancing Script | Clássica, elegante, peso forte |
| `greatvibes` | Great Vibes | Fina, fluida, sofisticada |
| `satisfy` | Satisfy | Formal, arredondada |
| `caveat` | Caveat | Manuscrita natural, casual |

### No template do certificado (mod_certificatebeautiful)

Adicione as tags no HTML do certificado via editor GrapesJS:

```html
<!-- Imagem da assinatura do aluno (tag completa) -->
{$USERSIGNATURE->signature_img}

<!-- Ou apenas a URL, para controle total do <img> -->
<img src="{$USERSIGNATURE->signature_url}" style="height:55px;">

<!-- Bloco condicional: só aparece se o aluno tem assinatura -->
<div data-sig-required="true">
    {$USERSIGNATURE->signature_img}
</div>
```

**Exemplo completo — seção de assinatura do aluno:**

```html
<div id="section-signature-student">
  <div data-sig-required="true">
    {$USERSIGNATURE->signature_img}
  </div>
  <div id="line-signature-student" style="border-top:1px solid #94a3b8;"></div>
  <p>{$USER->fullname}</p>
  <p>CPF: {$USER->idnumber}</p>
  <p>Participante</p>
</div>
```

---

## Desinstalação

### Via Interface Web

1. **Administração → Plugins → Visão geral dos plugins**
2. Localizar `certificatebeautifuldatainfo_usersignature` → **Desinstalar**
   - Todos os dados relacionados serão limpos automaticamente
3. Localizar `local_usersignature` → **Desinstalar**
   - O script `db/uninstall.php` removerá todos os arquivos PNG de assinatura do `moodledata` antes de apagar a tabela

> **Ordem obrigatória:** desinstalar o subplugin de certificado ANTES do `local_usersignature` (dependência).

### Via CLI

```bash
sudo -u www-data php /var/www/moodle/admin/cli/uninstall_plugins.php \
    --plugins=certificatebeautifuldatainfo_usersignature --run

sudo -u www-data php /var/www/moodle/admin/cli/uninstall_plugins.php \
    --plugins=local_usersignature --run

sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

---

## Privacidade (LGPD / GDPR)

- A assinatura é armazenada como arquivo PNG na área de usuário do `moodledata`
- Apenas o próprio usuário e administradores com `moodle/user:editprofile` podem acessar e editar
- Ao desinstalar o plugin, todos os arquivos de assinatura são removidos automaticamente

---

## Licença

GNU GPL v3 — veja [LICENSE](LICENSE)

---

## Suporte

Abra uma [issue no GitHub](https://github.com/glsoltec/moodle-signature-plugins/issues) ou entre em contato: dev@glsoltec.com.br
