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
$removeimage = optional_param('removeimage', 0, PARAM_BOOL);

$user    = \core_user::get_user($userid, '*', MUST_EXIST);
$context = \core\context\user::instance($userid);

require_login();
if ($userid != $USER->id) {
    require_capability('moodle/user:editprofile', $context);
}

// ─── Fontes disponíveis (mapa definido em lib.php) ────────────────────────────
$signaturefonts = local_usersignature_fonts();
$defaultfont    = local_usersignature_default_font();

// ─── Processar remoção da imagem importada (volta para fonte) ────────────────
if ($removeimage) {
    require_sesskey();
    local_usersignature_remove_imported_image($userid);
    redirect(
        new \moodle_url('/local/usersignature/index.php', ['userid' => $userid]),
        get_string('imageremoved', 'local_usersignature'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ─── Processar exclusão ───────────────────────────────────────────────────────
// require_sesskey() lança exceção se a sesskey for inválida (falha visível),
// ao contrário de confirm_sesskey() que ignoraria silenciosamente.
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $font_slug    = optional_param('selectedfont', '', PARAM_ALPHA);
    $sig_text     = required_param('signaturetext', PARAM_TEXT);
    $imagedata    = optional_param('imagedata', '', PARAM_RAW);

    // Origem escolhida na UI: 'font' (canvas) ou 'image' (arquivo importado).
    $source = optional_param('signaturesource', '', PARAM_ALPHA);
    if (!in_array($source, ['font', 'image'], true)) {
        $source = 'font';
    }

    // Modo imagem importada: o valor via file upload chega em imagedata como data URI.
    if ($source === 'image') {
        // A assinatura importada não deve ultrapassar 1 MiB.
        if (strlen($imagedata) > 1024 * 1024) {
            throw new \moodle_exception('invalidimage', 'local_usersignature');
        }
        if (!preg_match('/^data:image\/(png|jpeg|webp);base64,(.+)$/s', $imagedata, $m)) {
            throw new \moodle_exception('invalidimage', 'local_usersignature');
        }
        $image_data = base64_decode($m[2], true);
        if (!$image_data || strlen($image_data) < 100) {
            throw new \moodle_exception('invalidimage', 'local_usersignature');
        }
        $imageinfo = @getimagesizefromstring($image_data);
        if (!$imageinfo || !in_array($imageinfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
            || $imageinfo[0] > 2000 || $imageinfo[1] > 2000 || ($imageinfo[0] * $imageinfo[1]) > 4000000) {
            throw new \moodle_exception('invalidimage', 'local_usersignature');
        }
        $img = @imagecreatefromstring($image_data);
        if (!$img) {
            throw new \moodle_exception('invalidimage', 'local_usersignature');
        }
        imagedestroy($img);

        // Normaliza para PNG e salva como imagem importada.
        local_usersignature_save_imported_image($userid, local_usersignature_to_png($image_data));
        redirect(
            new \moodle_url('/local/usersignature/index.php', ['userid' => $userid]),
            get_string('signaturesaved', 'local_usersignature'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Fluxo de fonte (canvas) — comportamento original.
    // A assinatura é gerada pelo navegador e não precisa ultrapassar 1 MiB.
    if (strlen($imagedata) > 1024 * 1024) {
        throw new \moodle_exception('invalidtext', 'local_usersignature');
    }

    // Validações.
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
    $png_data = base64_decode($m[1], true);
    if (!$png_data || strlen($png_data) < 100) {
        throw new \moodle_exception('invalidtext', 'local_usersignature');
    }
    $imageinfo = @getimagesizefromstring($png_data);
    if (!$imageinfo || $imageinfo[2] !== IMAGETYPE_PNG || $imageinfo[0] > 2000 || $imageinfo[1] > 2000
        || ($imageinfo[0] * $imageinfo[1]) > 4000000) {
        throw new \moodle_exception('invalidtext', 'local_usersignature');
    }
    // Garante que os dados decodificados geram uma imagem válida.
    $img = @imagecreatefromstring($png_data);
    if (!$img) {
        throw new \moodle_exception('invalidtext', 'local_usersignature');
    }
    imagedestroy($img);

    // Salva como assinatura de fonte (signature.png) e volta origem para 'font'.
    $fs = get_file_storage();
    $fs->get_file($context->id, 'local_usersignature', 'signature', 0, '/', 'signature.png')?->delete();
    $fs->create_file_from_string([
        'contextid' => $context->id,
        'component' => 'local_usersignature',
        'filearea'  => 'signature',
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => 'signature.png',
    ], $png_data);

    local_usersignature_save_meta($userid, $font_slug, $sig_text, 'font');

    redirect(
        new \moodle_url('/local/usersignature/index.php', ['userid' => $userid]),
        get_string('signaturesaved', 'local_usersignature'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ─── Configurar página ────────────────────────────────────────────────────────
$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/local/usersignature/index.php', ['userid' => $userid]));
$PAGE->set_title(get_string('mysignature', 'local_usersignature'));
$PAGE->set_heading(fullname($user));
$PAGE->set_pagelayout('standard');

// ─── Estado atual ─────────────────────────────────────────────────────────────
$meta          = local_usersignature_get_signature_meta($userid);
$current_url   = local_usersignature_get_signature_url($userid);
$default_text  = $meta['text'] ?: fullname($user);
$selected_font = $meta['font'] ?: $defaultfont;
// Origem da assinatura: 'font' (canvas) ou 'image' (importada).
$signature_source = $meta['source'] === 'image' ? 'image' : 'font';
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
?>
<style>
<?= $fontfaces ?>
.sig-wrap           { max-width: 780px; margin: 0 auto; padding: 0 16px 48px; }
.sig-section-label  { font-size: .92rem; font-weight: 600; color: #475569; margin: 24px 0 8px; }
.sig-name-input     {
    display: block; width: 100%; box-sizing: border-box;
    padding: 10px 14px; font-size: 1.1rem;
    border: 2px solid #cbd5e1; border-radius: 8px;
    outline: none; transition: border-color .2s;
}
.sig-name-input:focus { border-color: #3b82f6; }
.sig-hint           { font-size: .78rem; color: #94a3b8; margin: 4px 0 0; }
.sig-grid           { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 4px; }
@media (max-width: 520px) { .sig-grid { grid-template-columns: 1fr; } }
.sig-card           {
    border: 2px solid #e2e8f0; border-radius: 12px;
    padding: 14px 10px 10px; cursor: pointer;
    background: #fff; transition: border-color .18s, box-shadow .18s;
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    user-select: none;
}
.sig-card:hover     { border-color: #93c5fd; box-shadow: 0 2px 8px rgba(59,130,246,.14); }
.sig-card.selected  { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37,99,235,.16); }
.sig-card input     { display: none; }
.sig-font-label     { font-size: .68rem; font-weight: 700; color: #64748b; letter-spacing: .06em; text-transform: uppercase; }
.sig-canvas         { width: 100%; height: 68px; display: block; }
.sig-current        {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 16px; margin-bottom: 4px; text-align: center; min-height: 56px;
}
.sig-current img    { max-height: 64px; }
.sig-actions        { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; align-items: center; }
.sig-btn-save       {
    background: #2563eb; color: #fff; border: none; border-radius: 8px;
    padding: 11px 28px; font-size: 1rem; font-weight: 600; cursor: pointer;
    transition: background .18s;
}
.sig-btn-save:hover { background: #1d4ed8; }
.sig-btn-delete     {
    background: #fff; color: #dc2626; border: 2px solid #fca5a5;
    border-radius: 8px; padding: 9px 20px; font-size: .9rem; font-weight: 600;
    cursor: pointer; transition: background .18s; text-decoration: none;
    display: inline-flex; align-items: center;
}
.sig-btn-delete:hover { background: #fef2f2; color: #dc2626; text-decoration: none; }
.sig-import-preview  {
    height: 68px; display: flex; align-items: center; justify-content: center;
    width: 100%; overflow: hidden;
}
.sig-import-preview img { max-height: 68px; max-width: 100%; }
.sig-import-placeholder {
    font-size: .72rem; font-weight: 700; letter-spacing: .06em; color: #94a3b8;
    border: 2px dashed #cbd5e1; border-radius: 8px; padding: 4px 10px;
}
</style>

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
        <input type="hidden" name="signaturesource" id="sig-source" value="<?= s($signature_source) ?>">

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
            <label class="sig-card <?= ($signature_source === 'font' && $slug === $selected_font) ? 'selected' : '' ?>"
                   id="card-<?= $slug ?>" data-font="<?= $slug ?>" data-source="font">
                <span class="sig-font-label"><?= s($info['label']) ?></span>
                <canvas class="sig-canvas" id="canvas-<?= $slug ?>" width="340" height="68"></canvas>
                <input type="radio" name="fontstyle" value="<?= $slug ?>"
                       <?= ($signature_source === 'font' && $slug === $selected_font) ? 'checked' : '' ?>>
            </label>
        <?php endforeach ?>

            <label class="sig-card <?= $signature_source === 'image' ? 'selected' : '' ?>"
                   id="card-import" data-source="image">
                <span class="sig-font-label"><?= get_string('importimage', 'local_usersignature') ?></span>
                <span class="sig-import-preview" id="sig-import-preview">
                    <?php if ($signature_source === 'image' && $current_url): ?>
                        <img src="<?= s($current_src) ?>" alt="<?= s(get_string('importimage', 'local_usersignature')) ?>">
                    <?php else: ?>
                        <span class="sig-import-placeholder">PNG</span>
                    <?php endif ?>
                </span>
                <input type="radio" name="fontstyle" id="sig-radio-import"
                       value="__import__"
                       <?= $signature_source === 'image' ? 'checked' : '' ?>>
            </label>
        </div>
        <input type="file" id="sig-file-input" accept="image/png" style="display:none">
        <p class="sig-hint" id="sig-import-hint" <?= $signature_source === 'image' ? '' : 'style="display:none"' ?>>
            <?= get_string('importimage_help', 'local_usersignature') ?>
        </p>

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
            <?php if ($signature_source === 'image'): ?>
            <a href="<?= (new \moodle_url('/local/usersignature/index.php', [
                'userid'      => $userid,
                'removeimage' => 1,
                'sesskey'     => sesskey(),
            ]))->out() ?>"
               class="sig-btn-delete">
                <?= get_string('removeimported', 'local_usersignature') ?>
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<script>
(function () {
    'use strict';

    const FONTS = <?= json_encode($signaturefonts) ?>;
    const W = 340, H = 68;
    const DEFAULT_TEXT = <?= json_encode($default_text) ?>;

    let currentFont = <?= json_encode($selected_font) ?>;
    let currentText = <?= json_encode($default_text) ?>;
    let source      = <?= json_encode($signature_source) ?>;
    let importedData = '';   // data URI da imagem importada selecionada.

    const sourceField = document.getElementById('sig-source');
    const importCard  = document.getElementById('card-import');
    const importHint  = document.getElementById('sig-import-hint');
    const fileInput   = document.getElementById('sig-file-input');
    const importPrev  = document.getElementById('sig-import-preview');

    // Limpa texto para apenas letras, acentos, espaço, hífen e ponto.
    function clean(t) {
        return (t || '').replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s\-\.]/g, '').substring(0, 60).trim();
    }

    function drawCanvas(slug, text) {
        const info   = FONTS[slug];
        const canvas = document.getElementById('canvas-' + slug);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, W, H);

        const safe = clean(text) || DEFAULT_TEXT;
        let fs = info.size;
        ctx.font = fs + 'px ' + info.family;
        while (ctx.measureText(safe).width > W - 24 && fs > 22) {
            fs -= 2;
            ctx.font = fs + 'px ' + info.family;
        }
        ctx.fillStyle     = info.color;
        ctx.textBaseline  = 'middle';
        ctx.textAlign     = 'center';
        ctx.fillText(safe, W / 2, H / 2);
    }

    function drawAll() {
        Object.keys(FONTS).forEach(s => drawCanvas(s, currentText));
    }

    // Gera PNG de alta resolução (2×) a partir da fonte selecionada.
    function buildPng() {
        const info = FONTS[currentFont];
        const hd   = document.createElement('canvas');
        hd.width   = W * 2;
        hd.height  = H * 2;
        const ctx  = hd.getContext('2d');
        ctx.scale(2, 2);

        const safe = clean(currentText);
        let fs = info.size;
        ctx.font = fs + 'px ' + info.family;
        while (ctx.measureText(safe).width > W - 24 && fs > 22) {
            fs -= 2;
            ctx.font = fs + 'px ' + info.family;
        }
        ctx.fillStyle    = info.color;
        ctx.textBaseline = 'middle';
        ctx.textAlign    = 'center';
        ctx.fillText(safe, W / 2, H / 2);
        return hd.toDataURL('image/png');
    }

    // Selecionar card (fonte ou imagem importada).
    document.querySelectorAll('.sig-card').forEach(card => {
        card.addEventListener('click', function () {
            document.querySelectorAll('.sig-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');

            if (this.dataset.source === 'image') {
                // Abre o seletor de arquivo; o import precisa de interação do usuário.
                fileInput.click();
            } else {
                source = 'font';
                sourceField.value = 'font';
                currentFont = this.dataset.font;
                document.getElementById('sig-selectedfont').value = currentFont;
                if (importHint) importHint.style.display = 'none';
            }
        });
    });

    // Ler arquivo PNG escolhido.
    fileInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) {
            // Sem arquivo: se estava selecionado "imagem", volta para fonte.
            if (source === 'image') {
                selectFontCard(currentFont);
            }
            return;
        }
        const reader = new FileReader();
        reader.onload = function (e) {
            importedData = e.target.result;
            source = 'image';
            sourceField.value = 'image';
            if (importPrev) importPrev.innerHTML = '<img src="' + importedData + '" alt="">';
            if (importHint) importHint.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    function selectFontCard(font) {
        source = 'font';
        sourceField.value = 'font';
        document.querySelectorAll('.sig-card').forEach(c => c.classList.remove('selected'));
        const target = document.getElementById('card-' + font);
        if (target) target.classList.add('selected');
        document.getElementById('sig-selectedfont').value = font;
        if (importHint) importHint.style.display = 'none';
    }

    // Atualizar pré-visualização ao digitar.
    let timer;
    document.getElementById('sig-text-input').addEventListener('input', function () {
        currentText = this.value;
        clearTimeout(timer);
        timer = setTimeout(drawAll, 100);
    });

    // Antes de submeter: gerar PNG no campo hidden conforme a origem.
    document.getElementById('sig-form').addEventListener('submit', function (e) {
        sourceField.value = source;
        if (source === 'image') {
            if (!importedData) {
                e.preventDefault();
                alert('<?= s(get_string('importimage_required', 'local_usersignature')) ?>');
                return;
            }
            document.getElementById('sig-imagedata').value = importedData;
        } else {
            // Gera PNG a partir da fonte ativa.
            document.getElementById('sig-imagedata').value = buildPng();
            document.getElementById('sig-selectedfont').value = currentFont;
        }
    });

    // Forçar o carregamento das fontes locais (@font-face) antes do primeiro
    // desenho: o canvas não dispara o download da fonte sozinho.
    if (document.fonts && document.fonts.load) {
        Promise.all(
            Object.values(FONTS).map(f => document.fonts.load('40px ' + f.family))
        ).then(drawAll).catch(drawAll);
    } else {
        setTimeout(drawAll, 800);
    }
}());
</script>
<?php
echo $OUTPUT->footer();
