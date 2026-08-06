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
    return 'caveat';
}

/**
 * Retorna a URL pública da assinatura do usuário, ou null se não existir.
 */
function local_usersignature_get_signature_url(int $userid): ?\moodle_url {
    $file = local_usersignature_get_active_file($userid);
    if (!$file) {
        return null;
    }
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
 * Retorna metadados da assinatura (estilo de fonte, texto e origem).
 */
function local_usersignature_get_signature_meta(int $userid): array {
    global $DB;
    $record = $DB->get_record('local_usersignature', ['userid' => $userid]);
    if (!$record) {
        return ['font' => '', 'text' => '', 'source' => 'font', 'timemodified' => 0];
    }
    return [
        'font'         => $record->font_style,
        'text'         => $record->signature_text,
        'source'       => $record->signature_source ?? 'font',
        'timemodified' => (int) $record->timemodified,
    ];
}

/**
 * Retorna o arquivo ativo da assinatura conforme o source (fonte ou imagem).
 *
 * - 'image': usa imported.png
 * - 'font' (padrão): usa signature.png (gerado por canvas/fonte)
 * Com fallback seguro: se o source aponta para arquivo inexistente, tenta o outro.
 */
function local_usersignature_get_active_file(int $userid): ?\stored_file {
    $fs = get_file_storage();
    $context = \core\context\user::instance($userid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }
    $meta = local_usersignature_get_signature_meta($userid);
    $filed = $meta['source'] === 'image' ? 'imported.png' : 'signature.png';

    $file = $fs->get_file($context->id, 'local_usersignature', 'signature', 0, '/', $filed);
    if ($file) {
        return $file;
    }
    // Fallback: tenta o outro arquivo. get_file() retorna false (não null)
    // quando não encontra; converter para null respeita a assinatura ?stored_file.
    $fallback = $filed === 'imported.png' ? 'signature.png' : 'imported.png';
    $file = $fs->get_file($context->id, 'local_usersignature', 'signature', 0, '/', $fallback);
    return $file ?: null;
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
    $file = local_usersignature_get_active_file($userid);
    if (!$file) {
        return '';
    }
    $mimetype = $file->get_mimetype();
    if ($mimetype !== 'image/png' && $mimetype !== 'image/jpeg' && $mimetype !== 'image/webp') {
        $mimetype = 'image/png';
    }
    return 'data:' . $mimetype . ';base64,' . base64_encode($file->get_content());
}

/**
 * Salva ou atualiza metadados da assinatura.
 *
 * @param int $userid
 * @param string $font Slug da fonte (vazio quando import por imagem).
 * @param string $text Texto da assinatura.
 * @param string $source 'font' ou 'image'.
 */
function local_usersignature_save_meta(int $userid, string $font, string $text, string $source = 'font'): void {
    global $DB;
    if (!in_array($source, ['font', 'image'], true)) {
        $source = 'font';
    }
    $existing = $DB->get_record('local_usersignature', ['userid' => $userid]);
    $now = time();
    if ($existing) {
        $existing->font_style        = $font;
        $existing->signature_text    = $text;
        $existing->signature_source  = $source;
        $existing->timemodified      = $now;
        $DB->update_record('local_usersignature', $existing);
    } else {
        $DB->insert_record('local_usersignature', (object)[
            'userid'          => $userid,
            'font_style'      => $font,
            'signature_text'  => $text,
            'signature_source'=> $source,
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);
    }
}

/**
 * Salva uma imagem (PNG) importada como assinatura do usuário.
 * Armazena como enviada (sem redimensionar); a renderização do certificado
 * limita a altura via max-height. Passa a origem para 'image'.
 */
function local_usersignature_save_imported_image(int $userid, string $pngdata): void {
    $context = \core\context\user::instance($userid, IGNORE_MISSING);
    if (!$context) {
        throw new \moodle_exception('invaliduser', 'local_usersignature');
    }
    $fs = get_file_storage();
    // Substitui o arquivo importado anterior (mantém signature.png intacto).
    $existed = $fs->get_file($context->id, 'local_usersignature', 'signature', 0, '/', 'imported.png');
    if ($existed) {
        $existed->delete();
    }
    $fs->create_file_from_string([
        'contextid' => $context->id,
        'component' => 'local_usersignature',
        'filearea'  => 'signature',
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => 'imported.png',
    ], $pngdata);

    $meta = local_usersignature_get_signature_meta($userid);
    local_usersignature_save_meta($userid, $meta['font'] ?: '', $meta['text'] ?: '', 'image');
}

/**
 * Converte dados de imagem (PNG/JPEG/WebP) para PNG, mantendo transparência.
 * Usada para normalizar a imagem importada antes de salvar.
 *
 * @param string $imagedata Dados binários da imagem original.
 * @return string Dados binários em PNG.
 * @throws \moodle_exception
 */
function local_usersignature_to_png(string $imagedata): string {
    $src = @imagecreatefromstring($imagedata);
    if (!$src) {
        throw new \moodle_exception('invalidimage', 'local_usersignature');
    }
    $width  = imagesx($src);
    $height = imagesy($src);

    $png = imagecreatetruecolor($width, $height);
    imagealphablending($png, false);
    imagesavealpha($png, true);
    $transparent = imagecolorallocatealpha($png, 0, 0, 0, 127);
    imagefill($png, 0, 0, $transparent);
    imagecopy($png, $src, 0, 0, 0, 0, $width, $height);
    imagedestroy($src);

    ob_start();
    imagepng($png, null, 9);
    $out = ob_get_clean();
    imagedestroy($png);

    return $out;
}

/**
 * Remove a imagem importada, voltando o source para 'font' (se houver fonte).
 */
function local_usersignature_remove_imported_image(int $userid): void {
    global $DB;
    $context = \core\context\user::instance($userid, IGNORE_MISSING);
    if ($context) {
        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'local_usersignature', 'signature', 0, '/', 'imported.png');
        if ($file) {
            $file->delete();
        }
    }
    $existing = $DB->get_record('local_usersignature', ['userid' => $userid]);
    if ($existing) {
        $existing->signature_source = 'font';
        $existing->timemodified     = time();
        $DB->update_record('local_usersignature', $existing);
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
