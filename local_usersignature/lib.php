<?php
defined('MOODLE_INTERNAL') || die();

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
        return ['font' => '', 'text' => ''];
    }
    return ['font' => $record->font_style, 'text' => $record->signature_text];
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
 * Adiciona o link "Minha Assinatura" ao menu de perfil do usuário.
 * Compatível com Moodle 4.x e 5.x (callback legado ainda suportado).
 */
function local_usersignature_extend_navigation_user(
    \navigation_node $navigation,
    \stdClass $user,
    \context_user $context
): void {
    global $USER;

    if ($USER->id != $user->id && !has_capability('moodle/user:editprofile', $context)) {
        return;
    }

    $url  = new \moodle_url('/local/usersignature/index.php', ['userid' => $user->id]);
    $node = \navigation_node::create(
        get_string('mysignature', 'local_usersignature'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_usersignature',
        new \pix_icon('i/edit', '')
    );
    $navigation->add_node($node);
}
