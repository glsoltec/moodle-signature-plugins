<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Fontes de assinatura disponíveis.
 * [slug => [label, family CSS, basename do arquivo, tamanho base px, cor hex]]
 *
 * Os arquivos são procurados em local/usersignature/fonts/ e em
 * $CFG->dataroot/fonts/ (ver font.php).
 */
function local_usersignature_fonts(): array {
    return [
        'autography' => ['label' => 'Autography', 'family' => "'Autography', cursive", 'file' => 'Autography', 'size' => 56, 'color' => '#2c4a1e'],
        'caveat'     => ['label' => 'Caveat',     'family' => "'Caveat', cursive",     'file' => 'Caveat',     'size' => 54, 'color' => '#1a2a4a'],
        'sacramento' => ['label' => 'Sacramento', 'family' => "'Sacramento', cursive", 'file' => 'Sacramento', 'size' => 56, 'color' => '#3a1a1a'],
        'aerotis'    => ['label' => 'Aerotis',    'family' => "'Aerotis', cursive",    'file' => 'Aerotis',    'size' => 50, 'color' => '#1a3a5c'],
    ];
}

/**
 * Slug da fonte padrão para todos os usuários.
 */
function local_usersignature_default_font(): string {
    return 'autography';
}

/**
 * Retorna a URL pública da assinatura do usuário, ou null se não existir.
 */
function local_usersignature_get_signature_url(int $userid): ?\moodle_url {
    $context = \core\context\user::instance($userid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }
    $fs    = get_file_storage();
    $files = $fs->get_area_files($context->id, 'local_usersignature', 'signature', 0, 'timemodified DESC', false);
    if (empty($files)) {
        return null;
    }
    $file = reset($files);
    return \moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * Retorna metadados da assinatura (estilo de fonte e texto).
 */
function local_usersignature_get_signature_meta(int $userid): array {
    global $DB;
    $record = $DB->get_record('local_usersignature', ['userid' => $userid]);
    if (!$record) {
        return ['font' => '', 'text' => '', 'timemodified' => 0];
    }
    return [
        'font'         => $record->font_style,
        'text'         => $record->signature_text,
        'timemodified' => (int) $record->timemodified,
    ];
}

/**
 * Retorna a assinatura como Data URI base64 (data:image/png;base64,...).
 *
 * Necessário para o PDF do certificado: o mPDF renderiza no servidor e NÃO
 * consegue baixar a URL do pluginfile (protegida por require_login). Embutir
 * a imagem em base64 garante que ela apareça no certificado.
 *
 * @param int $userid
 * @return string Data URI, ou '' se não houver assinatura.
 */
function local_usersignature_get_signature_datauri(int $userid): string {
    $context = \core\context\user::instance($userid, IGNORE_MISSING);
    if (!$context) {
        return '';
    }
    $fs    = get_file_storage();
    $files = $fs->get_area_files($context->id, 'local_usersignature', 'signature', 0, 'timemodified DESC', false);
    if (empty($files)) {
        return '';
    }
    $file = reset($files);
    return 'data:' . $file->get_mimetype() . ';base64,' . base64_encode($file->get_content());
}

/**
 * Salva ou atualiza metadados da assinatura.
 */
function local_usersignature_save_meta(int $userid, string $font, string $text): void {
    global $DB;
    $existing = $DB->get_record('local_usersignature', ['userid' => $userid]);
    $now = time();
    if ($existing) {
        $existing->font_style     = $font;
        $existing->signature_text = $text;
        $existing->timemodified   = $now;
        $DB->update_record('local_usersignature', $existing);
    } else {
        $DB->insert_record('local_usersignature', (object)[
            'userid'         => $userid,
            'font_style'     => $font,
            'signature_text' => $text,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ]);
    }
}

/**
 * Callback do Moodle para servir arquivos de assinatura.
 * URL: /pluginfile.php/{contextid}/local_usersignature/signature/0/signature.png
 */
function local_usersignature_pluginfile(
    $course, $cm, $context, string $filearea, array $args, bool $forcedownload, array $options = []
): void {
    global $USER;

    if ($context->contextlevel != CONTEXT_USER) {
        send_file_not_found();
    }
    if ($filearea !== 'signature') {
        send_file_not_found();
    }

    // Qualquer usuário logado pode ver a assinatura (necessário para o PDF do certificado).
    require_login(null, false);

    $fs       = get_file_storage();
    $itemid   = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file     = $fs->get_file($context->id, 'local_usersignature', 'signature', $itemid, $filepath, $filename);

    if (!$file) {
        send_file_not_found();
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Verifica se o usuário atual pode gerenciar a assinatura de $user.
 */
function local_usersignature_can_manage(\stdClass $user, \context_user $context): bool {
    global $USER;
    return $USER->id == $user->id || has_capability('moodle/user:editprofile', $context);
}

/**
 * Adiciona o link "Minha Assinatura" à navegação do PERFIL do usuário.
 * Callback chamado na página de perfil (/user/profile.php).
 *
 * Assinatura conforme Navigation API do Moodle 5.x:
 * https://moodledev.io/docs/5.1/apis/core/navigation
 *
 * SEM type hint em $course/$coursecontext: no perfil fora de curso o core
 * passa core\context\system (não context_course) — hint estrito causa TypeError.
 */
function local_usersignature_extend_navigation_user(
    \navigation_node $parentnode,
    \stdClass $user,
    \context_user $context,
    $course,
    $coursecontext
): void {
    if (!local_usersignature_can_manage($user, $context)) {
        return;
    }

    $parentnode->add(
        get_string('mysignature', 'local_usersignature'),
        new \moodle_url('/local/usersignature/index.php', ['userid' => $user->id]),
        \navigation_node::TYPE_SETTING,
        null,
        'local_usersignature',
        new \pix_icon('i/edit', '')
    );
}

/**
 * Adiciona o link "Minha Assinatura" ao menu de PREFERÊNCIAS/CONFIGURAÇÕES do usuário.
 * Callback chamado em /user/preferences.php.
 *
 * IMPORTANTE: o primeiro parâmetro é navigation_node (NÃO settings_navigation).
 * Type hint incorreto causa o TypeError em settings_navigation.php:1434 no Moodle 5.x.
 */
function local_usersignature_extend_navigation_user_settings(
    \navigation_node $parentnode,
    \stdClass $user,
    \context_user $context,
    $course,
    $coursecontext
): void {
    if (!local_usersignature_can_manage($user, $context)) {
        return;
    }

    $parentnode->add(
        get_string('mysignature', 'local_usersignature'),
        new \moodle_url('/local/usersignature/index.php', ['userid' => $user->id]),
        \navigation_node::TYPE_SETTING,
        null,
        'local_usersignature',
        new \pix_icon('i/edit', '')
    );
}
