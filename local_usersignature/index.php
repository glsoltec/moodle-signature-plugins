<?php
/**
 * Página de gerenciamento de assinatura cursiva do usuário.
 *
 * Fluxo:
 *   GET  → exibe o seletor de fontes com pré-visualização ao vivo
 *   POST → salva o PNG gerado no canvas + metadados no banco
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

// ─── Parâmetros ───────────────────────────────────────────────────────────────
$userid = optional_param('userid', $USER->id, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_BOOL);

$user    = \core_user::get_user($userid, '*', MUST_EXIST);
$context = \core\context\user::instance($userid);

require_login();
if ($userid != $USER->id) {
    require_capability('local/usersignature:manage', $context);
}

// ─── Fontes disponíveis (mapa definido em lib.php) ────────────────────────────
$signaturefonts = local_usersignature_fonts();
$defaultfont    = local_usersignature_default_font();

// ─── Configurar página (antes de processar ações) ─────────────────────────────
$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/local/usersignature/index.php', ['userid' => $userid]));
$PAGE->set_title(get_string('mysignature', 'local_usersignature'));
$PAGE->set_heading(fullname($user));
$PAGE->set_pagelayout('standard');

// ─── Processar exclusão ───────────────────────────────────────────────────────
if ($delete) {
    require_sesskey();
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'local_usersignature', 'signature');
    $DB->delete_records('local_usersignature', ['userid' => $userid]);
    redirect(
        new \moodle_url('/local/usersignature/index.php', ['userid' => $userid]),
        get_string('signaturedeleted', 'local_usersignature'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ─── Processar POST (salvar) ──────────────────────────────────────────────────
if ($PAGE->is_submitted()) {
    require_sesskey();

    $font_slug = required_param('selectedfont', PARAM_ALPHA);
    $sig_text  = required_param('signaturetext', PARAM_TEXT);
    $imagedata = required_param('imagedata', PARAM_RAW);

    if (!array_key_exists($font_slug, $signaturefonts)) {
        throw new \moodle_exception('invalidfont', 'local_usersignature');
    }
    $sig_text = preg_replace('/[^A-Za-zÀ-ÖØ-öø-ÿ\s\-\.]/u', '', $sig_text);
    $sig_text = trim(mb_substr($sig_text, 0, 60));
    if (mb_strlen($sig_text) < 2) {
        throw new \moodle_exception('invalidtext', 'local_usersignature');
    }
    if (!preg_match('/^data:image\/png;base64,(.+)$/s', $imagedata, $m)) {
        throw new \moodle_exception('invalidtext', 'local_usersignature');
    }
    $png_data = base64_decode($m[1]);
    if (!$png_data || strlen($png_data) < 100) {
        throw new \moodle_exception('invalidtext', 'local_usersignature');
    }
    $img = @imagecreatefromstring($png_data);
    if (!$img) {
        throw new \moodle_exception('invalidtext', 'local_usersignature');
    }
    imagedestroy($img);

    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'local_usersignature', 'signature');
    $fs->create_file_from_string([
        'contextid' => $context->id,
        'component' => 'local_usersignature',
        'filearea'  => 'signature',
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => 'signature.png',
    ], $png_data);

    local_usersignature_save_meta($userid, $font_slug, $sig_text);

    redirect(
        new \moodle_url('/local/usersignature/index.php', ['userid' => $userid]),
        get_string('signaturesaved', 'local_usersignature'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ─── Estado atual ─────────────────────────────────────────────────────────────
$meta          = local_usersignature_get_signature_meta($userid);
$current_url   = local_usersignature_get_signature_url($userid);
$default_text  = $meta['text'] ?: fullname($user);
$selected_font = $meta['font'] ?: $defaultfont;
// Assinaturas antigas podem referenciar fontes removidas (dancing, greatvibes...).
if (!array_key_exists($selected_font, $signaturefonts)) {
    $selected_font = $defaultfont;
}

// As fontes são servidas por font.php, que busca em local/usersignature/fonts/
// e em $CFG->dataroot/fonts/ (moodledata — não acessível via web diretamente).
$fontfaces = '';
foreach ($signaturefonts as $slug => $info) {
    $url = (new \moodle_url('/local/usersignature/font.php', ['font' => $slug]))->out(false);
    $fontfaces .= sprintf(
        "@font-face { font-family: %s; src: url('%s'); font-display: swap; }\n",
        trim(explode(',', $info['family'])[0]),
        $url
    );
}

// Cache-buster: força o navegador a recarregar a imagem após salvar.
// Sem isto, a URL fixa (itemid 0) é servida do cache e a alteração não aparece.
$current_src = $current_url
    ? $current_url->out(false, ['rev' => $meta['timemodified']])
    : '';

// ─── Renderizar ───────────────────────────────────────────────────────────────
echo $OUTPUT->header();

$PAGE->requires->css('/local/usersignature/styles.css');
$PAGE->requires->js_call_amd('local_usersignature/signature', 'init', [
    $signaturefonts,
    $selected_font,
    $default_text,
]);
?>
<style><?= $fontfaces ?></style>

<div class="sig-wrap">
    <?= $OUTPUT->heading(get_string('mysignature', 'local_usersignature'), 2) ?>

    <?php if ($current_url): ?>
    <p class="sig-section-label"><?= get_string('currentsignature', 'local_usersignature') ?></p>
    <div class="sig-current">
        <img src="<?= s($current_src) ?>" alt="<?= s(get_string('currentsignature', 'local_usersignature')) ?>">
    </div>
    <?php endif ?>

    <form id="sig-form" method="post" action="">
        <input type="hidden" name="sesskey"      value="<?= sesskey() ?>">
        <input type="hidden" name="userid"       value="<?= (int)$userid ?>">
        <input type="hidden" name="imagedata"    id="sig-imagedata"    value="">
        <input type="hidden" name="selectedfont" id="sig-selectedfont" value="<?= s($selected_font) ?>">

        <p class="sig-section-label"><?= get_string('signaturetext', 'local_usersignature') ?></p>
        <input type="text" id="sig-text-input" class="sig-name-input"
               name="signaturetext"
               value="<?= s($default_text) ?>"
               maxlength="60"
               placeholder="<?= s(fullname($user)) ?>">
        <p class="sig-hint"><?= get_string('signaturetext_help', 'local_usersignature') ?></p>

        <p class="sig-section-label" style="margin-top:20px;">
            <?= get_string('choosestyle', 'local_usersignature') ?>
        </p>

        <div class="sig-grid">
        <?php foreach ($signaturefonts as $slug => $info): ?>
            <label class="sig-card <?= ($slug === $selected_font) ? 'selected' : '' ?>"
                   id="card-<?= $slug ?>" data-font="<?= $slug ?>">
                <span class="sig-font-label"><?= s($info['label']) ?></span>
                <canvas class="sig-canvas" id="canvas-<?= $slug ?>" width="340" height="68"></canvas>
                <input type="radio" name="fontstyle" value="<?= $slug ?>"
                       <?= ($slug === $selected_font) ? 'checked' : '' ?>>
            </label>
        <?php endforeach ?>
        </div>

        <div class="sig-actions">
            <button type="submit" class="sig-btn-save">
                <?= get_string('savesignature', 'local_usersignature') ?>
            </button>
            <?php if ($current_url): ?>
            <a href="<?= (new \moodle_url('/local/usersignature/index.php', [
                'userid'  => $userid,
                'delete'  => 1,
                'sesskey' => sesskey(),
            ]))->out() ?>"
               class="sig-btn-delete"
               onclick="return confirm('<?= s(get_string('confirmdelete', 'local_usersignature')) ?>')">
                <?= get_string('deletesignature', 'local_usersignature') ?>
            </a>
            <?php endif ?>
        </div>
    </form>
</div>
<?php
echo $OUTPUT->footer();
